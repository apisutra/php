<?php

declare(strict_types=1);

use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\Requests\RetryAllowedRequest;
use ApiSutra\Tests\Stubs\Requests\RetryDeniedRequest;
use ApiSutra\Tests\Stubs\Requests\RetryDisabledRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\FakeSleeper;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Tests\Stubs\Requests\RetryInheritedRequest;
use ApiSutra\Tests\Stubs\Requests\RetryNullRequest;
use ApiSutra\Tests\Stubs\Requests\RetryIdempotentRequest;
use ApiSutra\Pipeline\Transport\RetryConfigResolver;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\ControlFlow\RetryableException;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\Tests\Stubs\Core\PsrNetworkFailure;
use GuzzleHttp\Psr7\Request;

it('применяет безопасную политику методов без дополнительных настроек', function (HttpMethod $method, int $calls): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make(['message' => 'fixture'], 503)]);
    $clock = new VirtualClock();
    $sleeper = new FakeSleeper($clock);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test',
        retry: new RetryConfig(attempts: 3, baseDelay: 10, jitter: false),
        authRetryOn401: false,
    ), $transport, $sleeper, $clock);
    $result = (new RetryPolicyRequest($method))->setClient($client)->send()->raw();
    expect($transport->getRecorded())->toHaveCount($calls)
        ->and($sleeper->calls)->toBe($calls - 1)
        ->and($result->errors->first()->code->value)->toBe('service_unavailable');
})->with([[HttpMethod::GET, 3], [HttpMethod::PUT, 3], [HttpMethod::DELETE, 3], [HttpMethod::POST, 1], [HttpMethod::PATCH, 1]]);

it('атрибут безопасности перекрывает политику клиента', function (string $class, array $methods, int $calls): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make([], 503)]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test',
        retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false, safeMethods: $methods),
    ), $transport, new FakeSleeper());
    (new $class(HttpMethod::POST))->setClient($client)->withRetry(2)->send()->raw();
    expect($transport->getRecorded())->toHaveCount($calls);
})->with([
    [RetryPolicyRequest::class, [HttpMethod::POST], 2],
    [RetryPolicyRequest::class, [], 1],
    [RetryAllowedRequest::class, [], 2],
    [RetryDeniedRequest::class, [HttpMethod::POST], 1],
    [RetryInheritedRequest::class, [HttpMethod::POST], 2],
    [RetryNullRequest::class, [HttpMethod::POST], 2],
    [RetryInheritedRequest::class, [], 1],
    [RetryNullRequest::class, [], 1],
    [RetryIdempotentRequest::class, [], 1],
]);

it('атрибут enabled false отключает общие повторы', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make([], 503)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig()), $transport, new FakeSleeper());
    (new RetryDisabledRequest())->setClient($client)->send()->raw();
    expect($transport->getRecorded())->toHaveCount(1);
});

it('сохраняет настройки клиента при атрибуте и runtime override', function (string $class): void {
    $config = new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(
        attempts: 2,
        baseDelay: 123,
        totalTimeoutMs: 999,
        retryExceptions: [LogicException::class],
        safeMethods: [],
    ));
    $resolver = new RetryConfigResolver($config);
    $request = new $class();
    $retry = $resolver->resolve($request, RequestOptions::empty()->withRetry(5));
    expect($retry->attempts)->toBe(5)
        ->and($retry->totalTimeoutMs)->toBe(999)
        ->and($retry->retryExceptions)->toBe([LogicException::class])
        ->and($retry->safeMethods)->toBe([])
        ->and($config->retry->attempts)->toBe(2);
})->with([RetryPolicyRequest::class, RetryInheritedRequest::class, RetryDisabledRequest::class]);

it('отклоняет неправильный тип метода в политике', function (): void {
    new RetryConfig(safeMethods: ['GET']);
})->throws(ConfigurationException::class);

it('RetryableException соблюдает безопасность отключение и лимит попыток', function (bool $safe, bool $enabled, int $expectedCalls): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $clock = new VirtualClock();
    $sleeper = new FakeSleeper($clock);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test',
        retry: new RetryConfig(attempts: 3, baseDelay: 10, jitter: false, safeMethods: $safe ? [HttpMethod::POST] : []),
    ), $transport, $sleeper, $clock);
    $original = new RetryableException('fixture retry', retryAfter: 1, maxAttempts: 2);
    $client->hooks()->on(Hook::AfterResponse, new class ($original) implements HookInterface {
        public function __construct(private RetryableException $exception)
        {
        }
        public function handle(PipelineContext $context): ?array
        {
            throw $this->exception;
        }
    });
    $request = (new RetryPolicyRequest(HttpMethod::POST))->setClient($client);
    if (!$enabled) {
        $request = $request->withoutRetry();
    }
    $result = $request->send()->raw();
    expect($result->exception)->toBe($original)
        ->and($result->isFailed())->toBeTrue()
        ->and($transport->getRecorded())->toHaveCount($expectedCalls)
        ->and($sleeper->totalMs)->toBe(($expectedCalls - 1) * 1000);
})->with([[true, true, 2], [false, true, 1], [true, false, 1]]);

it('сетевой сбой POST не обходит безопасность операции', function (): void {
    $original = new PsrNetworkFailure(new Request('POST', 'https://fixture.test'));
    $transport = new MockTransport();
    $transport->fake(['*' => static function () use ($original): never {
        throw $original;
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig()), $transport, new FakeSleeper());
    $result = (new RetryPolicyRequest(HttpMethod::POST))->setClient($client)->send()->raw();
    expect($transport->getRecorded())->toHaveCount(1)
        ->and($result->exception->getPrevious())->toBe($original)
        ->and($result->errors->first()->context['retryRefusalReason'])->toBe('operation_not_safe');
});

it('auth retry использует политику клиента даже при withoutRetry', function (bool $safe): void {
    $transport = new MockTransport();
    RefreshingAuthenticator::reset();
    $transport->fake([
        RefreshTokenRequest::class => MockResponse::success(['token' => 'fixture']),
        '*' => MockResponse::sequence([MockResponse::make([], 401), MockResponse::success(['ok' => true])]),
    ]);
    $clock = new VirtualClock();
    $sleeper = new FakeSleeper($clock);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test',
        auth: new RefreshingAuthenticator(),
        retry: new RetryConfig(safeMethods: $safe ? [HttpMethod::POST] : []),
        authRetryAttempts: 1,
    ), $transport, $sleeper, $clock);
    $result = (new RetryPolicyRequest(HttpMethod::POST))->setClient($client)->withoutRetry()->send()->raw();
    expect($transport->getRecorded())->toHaveCount($safe ? 3 : 1)
        ->and($result->isSuccess())->toBe($safe)
        ->and($sleeper->calls)->toBe(0);
})->with([false, true]);
