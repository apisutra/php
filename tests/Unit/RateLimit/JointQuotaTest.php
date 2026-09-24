<?php

declare(strict_types=1);

use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\Execution\BatchExecutor;
use ApiSutra\Execution\PoolExecutor;
use ApiSutra\RateLimiting\Backends\PhpRedisRateLimitBackend;
use ApiSutra\Tests\Support\UnsupportedTimeoutTransport;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Tests\Stubs\Requests\CacheableRequest;
use ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use ApiSutra\Tests\Stubs\Requests\CompositeAggregateRequest;
use ApiSutra\Tests\Stubs\Requests\DependsOnMainRequest;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\RateLimiting\Backends\LocalRateLimitBackend;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\RateLimiting\RateLimitDecision;
use ApiSutra\RateLimiting\RateLimitQuota;
use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\EmptyQuotaRequest;
use ApiSutra\Tests\Stubs\Requests\ExcludedOnlyQuotaRequest;
use ApiSutra\Tests\Stubs\Requests\ExcludedQuotaRequest;
use ApiSutra\Tests\Stubs\Requests\IncludedOnlyQuotaRequest;
use ApiSutra\Tests\Stubs\Requests\InvalidRateLimitRequest;
use ApiSutra\Tests\Stubs\Requests\JointQuotaRequest;
use ApiSutra\Tests\Stubs\Requests\KeyOnlyQuotaRequest;
use ApiSutra\Tests\Stubs\Requests\PartialQuotaRequest;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\Requests\ThrowOnlyQuotaRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\FailingRateLimitStore;
use ApiSutra\Tests\Support\ScriptedRateLimitBackend;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Transport\MockTransport;

function jointClient(ClientConfig $config, ?VirtualClock $clock = null): TestClient
{
    $clock ??= new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    return new TestClient($config, $transport, sleeper: $clock, clock: $clock);
}

it('совместно расходует 100 общих и 5 отчётов, deny не тратит общую квоту', function (): void {
    $client = jointClient(new ClientConfig(baseUrl: 'https://quota.test', rateLimit: new RateLimitConfig(100, 60, RateLimitBehavior::Throw)));
    for ($i = 0; $i < 5; $i++) {
        expect($client->send(new JointQuotaRequest())->raw()->isSuccess())->toBeTrue();
    }
    expect($client->send(new JointQuotaRequest())->raw()->errors->first()?->code)->toBe(ErrorCode::RateLimited);
    for ($i = 0; $i < 95; $i++) {
        expect($client->send(new RetryPolicyRequest())->raw()->isSuccess())->toBeTrue();
    }
    expect($client->send(new RetryPolicyRequest())->raw()->errors->first()?->code)->toBe(ErrorCode::RateLimited);
});

it('общий остаток ограничивает новую операцию раньше её собственного лимита', function (): void {
    $client = jointClient(new ClientConfig(baseUrl: 'https://quota.test', rateLimit: new RateLimitConfig(3, 60, RateLimitBehavior::Throw)));
    $client->send(new RetryPolicyRequest());
    expect($client->send(new JointQuotaRequest())->raw()->isSuccess())->toBeTrue()
        ->and($client->send(new JointQuotaRequest())->raw()->isSuccess())->toBeTrue()
        ->and($client->send(new JointQuotaRequest())->raw()->errors->first()?->code)->toBe(ErrorCode::RateLimited);
});

it('исключает только общую квоту, сохраняя собственную', function (): void {
    $client = jointClient(new ClientConfig(baseUrl: 'https://quota.test', rateLimit: new RateLimitConfig(1, 60, RateLimitBehavior::Throw)));
    expect($client->send(new ExcludedQuotaRequest())->raw()->isSuccess())->toBeTrue()
        ->and($client->send(new ExcludedQuotaRequest())->raw()->errors->first()?->code)->toBe(ErrorCode::RateLimited)
        ->and($client->send(new RetryPolicyRequest())->raw()->isSuccess())->toBeTrue();
});

it('разрешает участие отдельно и не создаёт фиктивной собственной квоты', function (string $class, bool $include, bool $common, int $expected): void {
    $backend = new ScriptedRateLimitBackend();
    $client = jointClient(new ClientConfig(baseUrl: 'https://quota.test', includeClientQuota: $include,
        rateLimit: $common ? new RateLimitConfig() : null, rateLimitBackend: $backend));
    expect($client->send(new $class())->raw()->isSuccess())->toBeTrue();
    expect($backend->calls)->toBe($expected === 0 ? 0 : 1);
    if ($expected > 0) {
        expect($backend->sets[0])->toHaveCount($expected)->and($backend->sets[0][0]->periodMs)->toBe(60000);
    }
})->with([
    [ExcludedOnlyQuotaRequest::class, true, true, 0],
    [EmptyQuotaRequest::class, true, true, 1],
    [IncludedOnlyQuotaRequest::class, false, true, 1],
    [IncludedOnlyQuotaRequest::class, true, false, 0],
    [JointQuotaRequest::class, true, false, 1],
    [JointQuotaRequest::class, true, true, 2],
]);

it('runtime меняет собственную квоту, без отключения общей; полный disable не вызывает backend', function (): void {
    $backend = new ScriptedRateLimitBackend();
    $client = jointClient(new ClientConfig(baseUrl: 'https://quota.test', rateLimit: new RateLimitConfig(100), rateLimitBackend: $backend));
    (new JointQuotaRequest())->setClient($client)->withRateLimit(7, 9)->send();
    expect(array_map(fn ($q) => $q->limit, $backend->sets[0]))->toBe([100, 7]);
    (new JointQuotaRequest())->setClient($client)->withoutRateLimit()->send();
    expect($backend->calls)->toBe(1);
    (new JointQuotaRequest())->setClient($client)->withoutRateLimit()->withRateLimit(8, 10)->send();
    expect($backend->calls)->toBe(2)->and($backend->sets[1][1]->limit)->toBe(8);
});

it('сохраняет backend и участие в with и допускает сброс backend', function (): void {
    $backend = new ScriptedRateLimitBackend();
    $config = new ClientConfig(baseUrl: 'https://quota.test', includeClientQuota: false, rateLimitBackend: $backend);
    $copy = $config->with(timeout: 5);
    expect($copy->rateLimitBackend)->toBe($backend)->and($copy->includeClientQuota)->toBeFalse()
        ->and($copy->with(rateLimitBackend: null)->rateLimitBackend)->toBeNull();
});

it('валидирует применимый атрибут при send, чтение metadata не падает', function (string $class): void {
    expect((new RequestSpecResolver())->resolveClass($class)->rateLimit)->not->toBeNull();
    $backend = new ScriptedRateLimitBackend();
    $client = jointClient(new ClientConfig(baseUrl: 'https://quota.test', rateLimitBackend: $backend));
    expect($client->send(new $class())->raw()->errors->first()?->code)->toBe(ErrorCode::ConfigurationError)
        ->and($backend->calls)->toBe(0);
})->with([InvalidRateLimitRequest::class, PartialQuotaRequest::class, KeyOnlyQuotaRequest::class, ThrowOnlyQuotaRequest::class]);

it('не игнорирует store общей квоты и наследует его только на одиночном пути', function (): void {
    $store = new FailingRateLimitStore();
    $config = new ClientConfig(baseUrl: 'https://quota.test', rateLimit: new RateLimitConfig(store: $store));
    $client = jointClient($config);
    expect($client->send(new JointQuotaRequest())->raw()->errors->first()?->code)->toBe(ErrorCode::ConfigurationError)
        ->and($store->reads)->toBe(0)->and($store->writes)->toBe(0);
    expect($client->send(new ExcludedQuotaRequest())->raw()->isSuccess())->toBeTrue()->and($store->writes)->toBe(1);
    expect(fn () => $config->with(rateLimitBackend: new ScriptedRateLimitBackend()))->toThrow(ConfigurationException::class);
});

it('локальные окна используют миллисекунды monotonic и не следуют скачкам wall time', function (): void {
    $clock = new VirtualClock();
    $backend = new LocalRateLimitBackend($clock);
    $quota = new RateLimitQuota('a', 1, 1000);
    expect($backend->tryAcquire([$quota])->granted)->toBeTrue();
    $clock->wallTime += 100000;
    $clock->milliseconds += 999;
    expect($backend->tryAcquire([$quota])->retryAfterMs)->toBe(1);
    $clock->wallTime -= 200000;
    $clock->milliseconds++;
    expect($backend->tryAcquire([$quota])->granted)->toBeTrue();
});

it('проверяет весь набор до записи и нормализует одинаковые ID', function (): void {
    $backend = new LocalRateLimitBackend(new VirtualClock());
    $a = new RateLimitQuota('a', 1, 1000);
    $b = new RateLimitQuota('b', 1, 1000);
    expect($backend->tryAcquire([$a, $a])->granted)->toBeTrue()
        ->and($backend->tryAcquire([$a, $b])->blockedIds)->toBe(['a'])
        ->and($backend->tryAcquire([$b])->granted)->toBeTrue();
    expect(fn () => $backend->tryAcquire([new RateLimitQuota('c', 1, 1000), new RateLimitQuota('b', 2, 1000)]))->toThrow(ConfigurationException::class);
    expect($backend->tryAcquire([new RateLimitQuota('c', 1, 1000)])->granted)->toBeTrue();
    expect(fn () => $backend->tryAcquire([$a, new RateLimitQuota('a', 2, 1000)]))->toThrow(ConfigurationException::class);
});

it('Throw сообщает максимум всех блокирующих окон с округлением вверх', function (): void {
    $clock = new VirtualClock();
    $backend = new LocalRateLimitBackend($clock);
    $quotas = [new RateLimitQuota('a', 1, 1200), new RateLimitQuota('b', 1, 9100)];
    $backend->tryAcquire($quotas);
    try {
        (new RateLimiter($clock, $clock, $backend))->acquireAll($quotas, ['a' => RateLimitBehavior::Throw, 'b' => RateLimitBehavior::Wait]);
        test()->fail('Ожидалось исключение');
    } catch (RateLimitException $exception) {
        expect($exception->retryAfter)->toBe(10)->and($clock->waits)->toBe([]);
    }
});

it('ожидает повторно весь набор, неблокирующий Throw не мешает Wait', function (): void {
    $clock = new VirtualClock();
    $backend = new LocalRateLimitBackend($clock);
    $a = new RateLimitQuota('a', 1, 1000);
    $b = new RateLimitQuota('b', 1, 2000);
    $backend->tryAcquire([$b]);
    (new RateLimiter($clock, $clock, $backend))->acquireAll([$a, $b], ['a' => RateLimitBehavior::Throw, 'b' => RateLimitBehavior::Wait]);
    expect($clock->waits)->toBe([2000])->and($backend->tryAcquire([$a])->granted)->toBeFalse();
});

it('deadline не допускает сон до конца бюджета', function (): void {
    $clock = new VirtualClock();
    $backend = new LocalRateLimitBackend($clock);
    $quota = new RateLimitQuota('a', 1, 1000);
    $backend->tryAcquire([$quota]);
    expect(fn () => (new RateLimiter($clock, $clock, $backend))->acquireAll([$quota], ['a' => RateLimitBehavior::Wait], new ExecutionBudget($clock, 1000)))
        ->toThrow(ExecutionDeadlineException::class);
    expect($clock->waits)->toBe([]);
});

it('backend failure и неверный decision терминальны и маскируются', function (string $mode): void {
    $backend = new ScriptedRateLimitBackend(static function () use ($mode): RateLimitDecision {
        return match ($mode) {
            'unknown' => new RateLimitDecision(false, ['unknown'], 1),
            'invalid' => new RateLimitDecision(false, [], 0),
            default => throw new RuntimeException('fixture-secret'),
        };
    });
    $client = jointClient(new ClientConfig(baseUrl: 'https://quota.test', rateLimit: new RateLimitConfig(), rateLimitBackend: $backend,
        retry: new RetryConfig(attempts: 3, retryExceptions: [Throwable::class])));
    $result = $client->send(new RetryPolicyRequest())->raw();
    expect($result->exception)->toBeInstanceOf(RateLimitBackendException::class)
        ->and($result->exception->getMessage())->not->toContain('fixture-secret')->and($backend->calls)->toBe(1);
})->with(['unknown', 'invalid', 'error']);

it('поздний grant прекращает выполнение без HTTP и повторного acquire', function (): void {
    $clock = new VirtualClock();
    $backend = new ScriptedRateLimitBackend(function () use ($clock) { $clock->advance(1001); return new RateLimitDecision(true); });
    $client = jointClient(new ClientConfig(baseUrl: 'https://quota.test', rateLimit: new RateLimitConfig(), rateLimitBackend: $backend,
        retry: new RetryConfig(totalTimeoutMs: 1000)), $clock);
    $result = $client->send(new RetryPolicyRequest())->raw();
    expect($result->errors->first()?->code)->toBe(ErrorCode::Timeout)->and($backend->calls)->toBe(1)
        ->and($backend->timeouts)->toBe([1000])->and($result->response)->toBeNull();
});

it('отказывает неподдерживаемому timeout до любого backend или PSR-16 store', function (bool $legacy): void {
    $store = new FailingRateLimitStore();
    $backend = new ScriptedRateLimitBackend();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://quota.test',
        rateLimit: new RateLimitConfig(store: $legacy ? $store : null), rateLimitBackend: $legacy ? null : $backend),
        new UnsupportedTimeoutTransport());
    expect($client->send(new RetryPolicyRequest())->raw()->errors->first()?->code)->toBe(ErrorCode::ConfigurationError)
        ->and($backend->calls)->toBe(0)->and($store->reads)->toBe(0)->and($store->writes)->toBe(0);
})->with([false, true]);

it('пересчитывает effective timeout после quota wait и retry backoff', function (): void {
    $clock = new VirtualClock();
    $backend = new ScriptedRateLimitBackend(static fn ($quotas, $timeout, $call) => $call === 1
        ? new RateLimitDecision(false, [$quotas[0]->key], 200) : new RateLimitDecision(true));
    $transport = new MockTransport();
    $attempts = 0;
    $transport->fake(['*' => function () use (&$attempts) {
        return MockResponse::make([], ++$attempts === 1 ? 503 : 200);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://quota.test', rateLimit: new RateLimitConfig(), rateLimitBackend: $backend,
        retry: new RetryConfig(attempts: 2, baseDelay: 300, maxDelay: 300, jitter: false, totalTimeoutMs: 1000)), $transport, $clock, $clock);
    expect($client->send(new RetryPolicyRequest())->raw()->isSuccess())->toBeTrue()
        ->and($clock->waits)->toBe([200, 300])->and($backend->calls)->toBe(3)
        ->and(array_map(fn ($r) => $r->transportOptions->timeoutMs, $transport->getRecorded()))->toBe([800, 500]);
});

it('cache hit не обращается к квотам, смена fake сохраняет локальный счётчик', function (): void {
    $backend = new ScriptedRateLimitBackend();
    $client = jointClient(new ClientConfig(baseUrl: 'https://quota.test', cacheConfig: new CacheConfig(store: new ArrayCache()),
        rateLimit: new RateLimitConfig(), rateLimitBackend: $backend));
    $client->send(new CacheableRequest('same'));
    $client->send(new CacheableRequest('same'));
    expect($backend->calls)->toBe(1);
    $local = jointClient(new ClientConfig(baseUrl: 'https://quota.test', rateLimit: new RateLimitConfig(1, 60, RateLimitBehavior::Throw)));
    $local->send(new RetryPolicyRequest());
    $local->fake(['*' => MockResponse::success([])]);
    expect($local->send(new RetryPolicyRequest())->raw()->errors->first()?->code)->toBe(ErrorCode::RateLimited);
});

it('атомарный отказ сохраняет предыдущий HTTP через все обёртки', function (string $mode, int $status): void {
    $backend = new ScriptedRateLimitBackend(static fn ($quotas, $timeout, $call) => $call === 1
        ? new RateLimitDecision(true) : new RateLimitDecision(false, [$quotas[0]->key], 60000));
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make(['error' => 'fixture'], $status)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://quota.test', auth: new BearerAuthenticator('fixture'),
        rateLimit: new RateLimitConfig(100, 60, RateLimitBehavior::Throw), rateLimitBackend: $backend,
        retry: new RetryConfig(attempts: 3, baseDelay: 0, maxDelay: 0, retryOn: [401, 503])), $transport, $clock, $clock);
    if ($mode === 'batch' || $mode === 'parallel') {
        $batch = new BatchExecutor(client: $client, requests: [new RetryPolicyRequest()]);
        $result = ($mode === 'parallel' ? $batch->parallel() : $batch)->send()->results()->all()[0];
    } elseif ($mode === 'pool') {
        $result = (new PoolExecutor(client: $client, requests: [new RetryPolicyRequest()], concurrency: 1))->send()->results()->all()[0];
    } elseif ($mode === 'async') {
        $result = $client->sendAsync(new RetryPolicyRequest())->wait()->raw();
    } else {
        $result = $client->send(new RetryPolicyRequest())->raw();
    }
    expect($result->errors->first()?->code)->toBe(ErrorCode::RateLimited)
        ->and($result->response?->status)->toBe($status)->and($backend->calls)->toBe(2)
        ->and($transport->getRecorded())->toHaveCount(1);
})->with(['sync', 'async', 'batch', 'parallel', 'pool'])->with([401, 503]);

it('отсутствующее расширение даёт понятную ошибку только при выборе Redis адаптера', function (): void {
    if (extension_loaded('redis')) { test()->markTestSkipped('Проверяется в job без redis'); }
    expect(fn () => new PhpRedisRateLimitBackend(new stdClass(), 'fixture'))->toThrow(ConfigurationException::class);
});

it('разделяет классы операций и kind, custom key объединяет только операции', function (): void {
    $backend = new ScriptedRateLimitBackend();
    $client = jointClient(new ClientConfig(baseUrl: 'https://quota.test',
        rateLimit: new RateLimitConfig(10, key: 'same'), rateLimitBackend: $backend));
    $client->send((new JointQuotaRequest())->withRateLimit(5, 60));
    $client->send((new JointQuotaRequest())->withRateLimit(5, 60));
    $client->send((new RetryPolicyRequest())->withRateLimit(5, 60));
    $client->send((new JointQuotaRequest())->withRateLimit(5, 60, key: 'same'));
    $client->send((new RetryPolicyRequest())->withRateLimit(5, 60, key: 'same'));
    expect($backend->sets[0][1]->key)->toBe($backend->sets[1][1]->key)
        ->not->toBe($backend->sets[2][1]->key)
        ->and($backend->sets[3][1]->key)->toBe($backend->sets[4][1]->key)
        ->not->toBe($backend->sets[3][0]->key);
});

it('при пагинации, depends-on и composite учитывает только фактические дочерние HTTP', function (string $mode): void {
    $backend = new ScriptedRateLimitBackend();
    $client = jointClient(new ClientConfig(baseUrl: 'https://quota.test', rateLimit: new RateLimitConfig(), rateLimitBackend: $backend));
    $client->fake(['*' => MockResponse::success(['data' => [1], 'meta' => ['has_more' => true, 'next_cursor' => 'next'], 'token' => 'fixture', 'name' => 'fixture', 'id' => 1])]);
    if ($mode === 'pages') {
        $result = (new PaginatedRequest())->setClient($client)->paginate()->pages(2);
    } else {
        $result = ($mode === 'composite' ? new CompositeAggregateRequest() : new DependsOnMainRequest())->setClient($client)->send()->raw();
    }
    expect($result->isSuccess())->toBeTrue()->and($backend->calls)->toBe(2);
})->with(['pages', 'composite', 'dependency']);

it('истёкшие локальные окна не мешают новым определениям и переполнение не расходует набор', function (): void {
    $clock = new VirtualClock();
    $backend = new LocalRateLimitBackend($clock);
    expect($backend->tryAcquire([new RateLimitQuota('old', 1, 1)])->granted)->toBeTrue();
    $clock->advance(1);
    expect($backend->tryAcquire([new RateLimitQuota('old', 2, 2)])->granted)->toBeTrue();
    $clock->milliseconds = PHP_INT_MAX - 1;
    expect(fn () => $backend->tryAcquire([new RateLimitQuota('short', 1, 1), new RateLimitQuota('overflow', 1, 2)]))
        ->toThrow(ConfigurationException::class);
    expect($backend->tryAcquire([new RateLimitQuota('short', 1, 1)])->granted)->toBeTrue();
});

it('auth refresh учитывает свой HTTP, даже если основная операция исключена из общей квоты', function (): void {
    RefreshingAuthenticator::reset();
    $backend = new ScriptedRateLimitBackend();
    $transport = new MockTransport();
    $transport->fake([
        ExcludedOnlyQuotaRequest::class => MockResponse::sequence([
            MockResponse::make('original', 401), MockResponse::success(['ok' => true]),
        ]),
        RefreshTokenRequest::class => MockResponse::success(['token' => 'fixture-token']),
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://quota.test', auth: new RefreshingAuthenticator(),
        rateLimit: new RateLimitConfig(), rateLimitBackend: $backend), $transport);
    try {
        expect($client->send(new ExcludedOnlyQuotaRequest())->raw()->isSuccess())->toBeTrue()
            ->and($transport->getRecorded())->toHaveCount(3)->and($backend->calls)->toBe(1);
    } finally {
        RefreshingAuthenticator::reset();
    }
});
