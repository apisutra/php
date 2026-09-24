<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Stubs\Requests\CacheableRequest;
use ApiSutra\Tests\Support\SpyCache;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Timing\ExecutionDeadline;
use ApiSutra\Timing\SystemClock;
use ApiSutra\Transport\MockTransport;

it('делит внешний срок между тремя независимыми вызовами', function (bool $async): void {
    $clock = new VirtualClock();
    $deadline = ExecutionDeadline::afterMs(1000, $clock);
    $transport = new MockTransport();
    $timeouts = [];
    $transport->fake(['*' => static function () use ($clock, $transport, &$timeouts): MockResponse {
        $sent = $transport->getRecorded();
        $remaining = $sent[array_key_last($sent)]->transportOptions->effective()->timeoutMs;
        $timeouts[] = $remaining;
        $clock->advance(min(400, $remaining));
        return MockResponse::success(['ok' => true]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(totalTimeoutMs: 5000)), $transport, $clock, $clock);
    for ($i = 0; $i < 3; $i++) {
        // Другой клиент с теми же часами также не перезапускает срок.
        $caller = $i === 1 ? new TestClient($client->getConfig(), $transport, $clock, $clock) : $client;
        $result = ($async ? $caller->sendAsync((new RetryPolicyRequest())->withDeadline($deadline))->wait() : $caller->send((new RetryPolicyRequest())->withDeadline($deadline)))->raw();
        expect($result->isSuccess())->toBe($i < 2);
    }
    expect($timeouts)->toBe([1000, 600, 200])->and($clock->milliseconds)->toBe(2000)
        ->and($result->errors->first()->context['reason'])->toBe('execution_deadline_exceeded')
        ->and($result->errors->first()->context['transmissionState'])->toBe('unknown');
})->with([false, true]);

it('отклоняет истёкший срок до HTTP и снимает только runtime опцию', function (bool $throw): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', throwOnErrors: $throw, retry: new RetryConfig(totalTimeoutMs: 700)), $transport, $clock, $clock);
    $request = (new RetryPolicyRequest())->setClient($client);
    $execution = $request->withDeadline(ExecutionDeadline::afterMs(0, $clock))->withHeader('X-Fixture', 'value');
    if ($throw) {
        try {
            $execution->send()->raw();
            test()->fail('Ожидался deadline');
        } catch (ExecutionDeadlineException $exception) {
            expect($exception->stage)->toBe('started')->and($exception->transmissionState->value)->toBe('not_sent');
        }
    } else {
        $result = $execution->send()->raw();
        expect($result->errors->first()->context)->toMatchArray(['stage' => 'started', 'reason' => 'execution_deadline_exceeded', 'transmissionState' => 'not_sent']);
    }
    expect($transport->getRecorded())->toBe([]);
    expect($execution->withoutDeadline()->send()->raw()->isSuccess())->toBeTrue();
    expect($transport->getRecorded()[0]->transportOptions->effective()->timeoutMs)->toBe(700);
    expect($request->send()->raw()->isSuccess())->toBeTrue();
})->with([false, true]);

it('берёт минимум parent client external и проверяет шкалы времени', function (): void {
    $clock = new VirtualClock();
    $parent = new ExecutionBudget($clock, 800);
    $clock->advance(100);
    expect((new ExecutionBudget($clock, 300, $parent, deadline: ExecutionDeadline::afterMs(900, $clock)))->remainingMs())->toBe(300);
    expect((new ExecutionBudget($clock, 900, $parent, deadline: ExecutionDeadline::afterMs(900, $clock)))->remainingMs())->toBe(700);
    expect((new ExecutionBudget($clock, 900, $parent, deadline: ExecutionDeadline::afterMs(200, $clock)))->remainingMs())->toBe(200);
    $clock->wallTime += 3600;
    expect($parent->remainingMs())->toBe(700);
    expect(fn () => new ExecutionBudget(new VirtualClock(), deadline: ExecutionDeadline::afterMs(1000, $clock)))->toThrow(ConfigurationException::class);
    ExecutionDeadline::afterMs(1000)->assertCompatible(new SystemClock());
    expect(fn () => ExecutionDeadline::afterMs(-1))->toThrow(ConfigurationException::class);
    expect(fn () => ExecutionDeadline::afterMs(PHP_INT_MAX))->toThrow(ConfigurationException::class);
});

it('сохраняет дедлайн без retry и не ждёт сверх остатка', function (string $wait): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => $wait === 'retry' ? MockResponse::make('{}', 429, ['Retry-After' => '1']) : MockResponse::success()]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test',
        retry: $wait === 'retry' ? new RetryConfig(baseDelay: 0, jitter: false) : null,
        rateLimit: $wait === 'quota' ? new RateLimitConfig(1, 60) : null,
    ), $transport, $clock, $clock);
    if ($wait === 'quota') {
        $client->send(new RetryPolicyRequest())->raw();
    }
    $execution = (new RetryPolicyRequest())->withDeadline(ExecutionDeadline::afterMs(1000, $clock));
    if ($wait === 'delay') {
        $execution = $execution->withDelay(1000)->withoutRetry();
    }
    $result = $client->send($execution)->raw();
    expect($result->errors->first()->context['reason'])->toBe('execution_deadline_exceeded')
        ->and($clock->waits)->toBe([])->and($clock->milliseconds)->toBe(1000)
        ->and($transport->getRecorded())->toHaveCount($wait === 'delay' ? 0 : 1);
})->with(['retry', 'quota', 'delay']);

it('отказывает несовместимым часам до HTTP', function (): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport, $clock, $clock);
    $result = $client->send((new RetryPolicyRequest())->withDeadline(ExecutionDeadline::afterMs(1000, new VirtualClock())))->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')->and($transport->getRecorded())->toBe([]);
});

it('auth refresh наследует внешний срок, а не новый клиентский лимит', function (): void {
    RefreshingAuthenticator::reset();
    $clock = new VirtualClock();
    $deadline = ExecutionDeadline::afterMs(1000, $clock);
    $transport = new MockTransport();
    $remaining = null;
    $transport->fake(['*' => static function () use ($clock, $transport, &$remaining): MockResponse {
        $requests = $transport->getRecorded();
        $request = $requests[array_key_last($requests)];
        if (str_ends_with($request->url, '/refresh')) {
            $remaining = $request->transportOptions->effective()->timeoutMs;
            $clock->advance($remaining);
            return MockResponse::success(['token' => 'fixture']);
        }
        $clock->advance(400);
        return MockResponse::make('{}', 401);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', auth: new RefreshingAuthenticator(), retry: new RetryConfig(totalTimeoutMs: 5000)), $transport, $clock, $clock);
    $result = $client->send((new RetryPolicyRequest())->withDeadline($deadline))->raw();
    expect($remaining)->toBe(600)->and($clock->milliseconds)->toBe(2000)
        ->and($result->exception)->toBeInstanceOf(ExecutionDeadlineException::class)
        ->and($transport->getRecorded())->toHaveCount(2);
});

it('истёкший срок не читает кеш и не запускает auth или BeforeSend', function (): void {
    RefreshingAuthenticator::reset();
    $clock = new VirtualClock();
    $cache = new SpyCache();
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', cacheConfig: new CacheConfig(store: $cache), auth: new RefreshingAuthenticator()), $transport, $clock, $clock);
    $hook = new class implements HookInterface {
        public int $calls = 0;
        public function handle(PipelineContext $context): ?array { $this->calls++; return null; }
    };
    $client->hooks()->on(Hook::BeforeSend, $hook);
    $client->send((new CacheableRequest('fixture'))->withDeadline(ExecutionDeadline::afterMs(0, $clock)))->raw();
    expect($cache->lastGetKey)->toBeNull()->and($cache->lastSetKey)->toBeNull()
        ->and(RefreshingAuthenticator::$authenticateCalls)->toBe(0)->and($hook->calls)->toBe(0)
        ->and($transport->getRecorded())->toBe([]);
});

it('обнаруживает превышение в блокирующем hook после возврата управления', function (): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport, $clock, $clock);
    $client->hooks()->on(Hook::BeforeSend, new class($clock) implements HookInterface {
        public function __construct(private VirtualClock $clock) {}
        public function handle(PipelineContext $context): ?array { $this->clock->advance(1200); return null; }
    });
    $result = $client->send((new RetryPolicyRequest())->withDeadline(ExecutionDeadline::afterMs(1000, $clock)))->raw();
    expect($clock->milliseconds)->toBe(2200)->and($result->exception)->toBeInstanceOf(ExecutionDeadlineException::class)
        ->and($transport->getRecorded())->toBe([]);
});
