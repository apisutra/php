<?php

declare(strict_types=1);

use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use ApiSutra\Exceptions\Request\ClientException;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Exceptions\Request\RequestException;
use ApiSutra\Execution\BatchExecutor;
use ApiSutra\Execution\PoolExecutor;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\InvalidRateLimitRequest;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\FailingRateLimitStore;
use ApiSutra\Tests\Support\StrictCache;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;
use Psr\Log\AbstractLogger;

it('возвращает локальную квоту без вымышленного HTTP и сохраняет catch и retryAfter', function (bool $async, bool $throw): void {
    $clock = new VirtualClock();
    $store = new FailingRateLimitStore();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', throwOnErrors: $throw,
        rateLimit: new RateLimitConfig(limit: 1, behavior: RateLimitBehavior::Throw, store: $store),
        retry: new RetryConfig(attempts: 3, baseDelay: 0, maxDelay: 0, retryOn: [401, 503], retryExceptions: [Throwable::class]),
    ), $transport, $clock, $clock);
    $client->send(new RetryPolicyRequest())->raw();
    $send = fn () => ($async ? $client->sendAsync(new RetryPolicyRequest())->wait() : $client->send(new RetryPolicyRequest()))->raw();
    if ($throw) {
        try {
            $send();
            test()->fail('Ожидалось исключение');
        } catch (RateLimitException $exception) {
            expect($exception->response)->toBeNull()->and($exception->retryAfter)->toBe(60);
        }
    } else {
        $result = $send();
        $error = $result->errors->first();
        expect($error?->code)->toBe(ErrorCode::RateLimited)
            ->and($error?->context['reason'])->toBe('local_rate_limit_exceeded')
            ->and($error?->context['retryAfter'])->toBe(60)
            ->and($error?->context['httpStatus'] ?? null)->toBeNull()
            ->and($error?->response)->toBeNull()->and($result->response)->toBeNull()
            ->and($result->exception)->toBeInstanceOf(RateLimitException::class)
            ->toBeInstanceOf(ClientException::class)->toBeInstanceOf(RequestException::class);
        expect(fn () => $result->throw())->toThrow(RateLimitException::class);
    }
    expect($transport->getRecorded())->toHaveCount(1)->and($store->reads)->toBe(2)
        ->and($store->writes)->toBe(1)->and($clock->waits)->toBe([]);
})->with([false, true])->with([false, true]);

it('останавливает HTTP при отказе backend и не раскрывает previous в обычной диагностике', function (string $failure): void {
    $store = new FailingRateLimitStore();
    $store->failure = $failure;
    $logger = new class extends AbstractLogger {
        /** @var list<array<string, mixed>> */
        public array $records = [];
        public function log(mixed $level, Stringable|string $message, array $context = []): void
        {
            $this->records[] = ['message' => (string) $message, 'context' => $context];
        }
    };
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', logger: $logger,
        rateLimit: new RateLimitConfig(store: $store),
        retry: new RetryConfig(attempts: 3, baseDelay: 0, maxDelay: 0, retryOn: [401, 503], retryExceptions: [Throwable::class]),
    ), $transport);
    $result = $client->send(new RetryPolicyRequest())->raw();
    $error = $result->errors->first();
    expect($error?->code)->toBe(ErrorCode::ExecutionError)
        ->and($error?->context['reason'])->toBe('rate_limit_backend_error')
        ->and($error?->context['stage'])->toBe('rate_limit_store')
        ->and($result->response)->toBeNull()->and($result->exception)->toBeInstanceOf(RateLimitBackendException::class)
        ->and($transport->getRecorded())->toBe([])->and($store->reads)->toBe(1)
        ->and($store->writes)->toBe($failure === 'read' ? 0 : 1)
        ->and(json_encode([$error?->message, $error?->context, $logger->records], JSON_THROW_ON_ERROR))->not->toContain('fixture-store-secret');
    if ($failure !== 'false') {
        expect($result->exception?->getPrevious()?->getMessage())->toBe('fixture-store-secret');
    }
})->with(['read', 'write', 'false']);

it('сохраняет фактический ответ при локальном отказе следующей попытки во всех обёртках', function (string $mode, bool $throw, string $failure, int $status): void {
    $clock = new VirtualClock();
    $store = new FailingRateLimitStore();
    $transport = new MockTransport();
    $transport->fake(['*' => static function () use ($store, $failure, $status): MockResponse {
        $store->failure = $failure === 'quota' ? null : $failure;
        return MockResponse::make(['error' => 'fixture'], $status);
    }]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', throwOnErrors: $throw, auth: new BearerAuthenticator('fixture-token'),
        rateLimit: new RateLimitConfig(limit: $failure === 'quota' ? 1 : 2, behavior: RateLimitBehavior::Throw, store: $store),
        retry: new RetryConfig(attempts: 3, baseDelay: 0, maxDelay: 0, retryOn: [401, 503], retryExceptions: [Throwable::class]),
    ), $transport, $clock, $clock);
    try {
        if ($mode === 'batch' || $mode === 'parallel') {
            $batch = new BatchExecutor(client: $client, requests: [new RetryPolicyRequest()]);
            $result = ($mode === 'parallel' ? $batch->parallel() : $batch)->send()->results()->all()[0];
        } elseif ($mode === 'pool') {
            $result = (new PoolExecutor(client: $client, requests: [new RetryPolicyRequest()], concurrency: 1))->send()->results()->all()[0];
        } else {
            $result = $client->send(new RetryPolicyRequest())->raw();
        }
        expect($throw)->toBeFalse();
    } catch (RateLimitException|RateLimitBackendException $exception) {
        expect($throw)->toBeTrue()
            ->and($exception->lastResponse?->status)->toBe($status);
        expect($transport->getRecorded())->toHaveCount(1)->and($store->reads)->toBe(2);
        return;
    }
    $error = $result->errors->first();
    expect($result->response?->status)->toBe($status)->and($error?->response)->toBe($result->response)
        ->and($error?->context['httpStatus'])->toBe($status)
        ->and($error?->code)->toBe($failure === 'quota' ? ErrorCode::RateLimited : ErrorCode::ExecutionError)
        ->and($error?->context['reason'])->toBe($failure === 'quota' ? 'local_rate_limit_exceeded' : 'rate_limit_backend_error')
        ->and($transport->getRecorded())->toHaveCount(1)->and($store->reads)->toBe(2)->and($clock->waits)->toBe([]);
    if ($failure === 'quota') {
        expect($result->exception->response)->toBeNull()->and($error?->context['retryAfter'])->toBe(60);
    }
})->with(['sync', 'batch', 'parallel', 'pool'])->with([false, true])->with(['quota', 'read', 'write', 'false'])->with([503, 401]);

it('проверяет атрибут до HTTP и записи квоты', function (): void {
    $transport = new MockTransport();
    $store = new FailingRateLimitStore();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', rateLimit: new RateLimitConfig(store: $store)), $transport);
    $result = $client->send(new InvalidRateLimitRequest())->raw();
    expect($result->errors->first()?->code)->toBe(ErrorCode::ConfigurationError)
        ->and($transport->getRecorded())->toBe([])->and($store->reads)->toBe(0)->and($store->writes)->toBe(0);
});

it('сохраняет группировку custom key разных baseUrl в строгом PSR-16 store', function (): void {
    $store = new StrictCache();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $rate = new RateLimitConfig(limit: 1, behavior: RateLimitBehavior::Throw, store: $store, key: 'fixture:/custom?key');
    $first = new TestClient(new ClientConfig(baseUrl: 'https://one.fixture.test', rateLimit: $rate), $transport);
    $second = new TestClient(new ClientConfig(baseUrl: 'https://two.fixture.test', rateLimit: $rate), $transport);
    expect($first->send(new RetryPolicyRequest())->raw()->isSuccess())->toBeTrue()
        ->and($second->send(new RetryPolicyRequest())->raw()->errors->first()?->code)->toBe(ErrorCode::RateLimited)
        ->and($transport->getRecorded())->toHaveCount(1)->and($store->keys)->toHaveCount(1);
    expect($store->keys[0])->toMatch('/^[0-9a-f]{64}$/D')->not->toContain('fixture');
});

it('сохраняет обработку реального HTTP 429 и Retry-After', function (bool $retry): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::sequence([
        MockResponse::make(['error' => 'slow down'], 429, ['Retry-After' => '2']),
        MockResponse::success(['ok' => true]),
    ])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', rateLimit: new RateLimitConfig(),
        retry: new RetryConfig(attempts: $retry ? 2 : 1, baseDelay: 0, maxDelay: 0),
    ), $transport, $clock, $clock);
    $result = $client->send(new RetryPolicyRequest())->raw();
    if ($retry) {
        expect($result->isSuccess())->toBeTrue()->and($transport->getRecorded())->toHaveCount(2)
            ->and($clock->waits)->toBe([2000]);
    } else {
        expect($result->response?->status)->toBe(429)->and($result->exception)->toBeInstanceOf(RateLimitException::class)
            ->and($result->exception->response)->toBe($result->response)->and($result->exception->retryAfter)->toBe(2)
            ->and($result->errors->first()?->context['reason'] ?? null)->toBeNull();
    }
})->with([false, true]);
