<?php

declare(strict_types=1);

use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Support\ExecutionDelivery;
use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Execution\BatchExecutor;
use ApiSutra\Execution\PoolExecutor;
use ApiSutra\Auth\TokenAuthenticator;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Exceptions\Auth\AuthRefreshFailedException;
use ApiSutra\Exceptions\Request\UnauthorizedException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Stubs\Requests\ProtectedRequest;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

it('без доступного refresh сохраняет один настоящий 401', function (string $kind, bool $async): void {
    $auth = match ($kind) {
        'bearer' => new BearerAuthenticator('fixture'),
        'token' => new TokenAuthenticator('fixture', 'password'),
        default => null,
    };
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('original', 401, ['X-Origin' => 'main'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', auth: $auth), $transport);
    $request = new ProtectedRequest();
    $result = $async ? $client->sendAsync($request)->wait()->raw() : $client->send($request)->raw();
    expect($transport->getRecorded())->toHaveCount(1)
        ->and($result->response->body)->toBe('original')
        ->and($result->response->headers['X-Origin'])->toBe(['main']);
})->with(['none', 'bearer', 'token'])->with([false, true]);

it('восстанавливает авторизацию в контексте send без setClient', function (bool $async): void {
    RefreshingAuthenticator::reset();
    $transport = new MockTransport();
    $transport->fake([
        ProtectedRequest::class => MockResponse::sequence([MockResponse::make('original', 401), MockResponse::success(['id' => 1, 'name' => 'ok'])]),
        RefreshTokenRequest::class => MockResponse::success(['token' => 'token']),
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', auth: new RefreshingAuthenticator()), $transport);
    $result = $async ? $client->sendAsync(new ProtectedRequest())->wait()->raw() : $client->send(new ProtectedRequest())->raw();
    expect($result->isSuccess())->toBeTrue()
        ->and(RefreshingAuthenticator::$refreshCalls)->toBe(1)
        ->and($transport->getRecorded())->toHaveCount(3);
})->with([false, true]);

it('отказ refresh сохраняет основной 401 и результат зависимости без слепого повтора', function (bool $throw, bool $async, string $failure): void {
    RefreshingAuthenticator::reset();
    $transport = new MockTransport();
    $transport->fake([
        ProtectedRequest::class => MockResponse::make('original', 401, ['X-Origin' => 'main']),
        RefreshTokenRequest::class => match ($failure) {
            'http' => MockResponse::make('refresh failure', 503, ['X-Origin' => 'refresh']),
            'empty' => MockResponse::make('', 204),
            default => MockResponse::make('{broken', 200, ['Content-Type' => 'application/json']),
        },
    ]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', auth: new RefreshingAuthenticator(), throwOnErrors: $throw,
        retry: new RetryConfig(attempts: 2, baseDelay: 0, retryOn: [], retryExceptions: [Throwable::class]),
        environment: Environment::Testing,
    ), $transport);
    try {
        $result = $async ? $client->sendAsync(new ProtectedRequest())->wait()->raw() : $client->send(new ProtectedRequest())->raw();
        expect($throw)->toBeFalse();
        $exception = $result->exception;
        expect($result->errors->first()->context['reason'])->toBe('auth_refresh_failed');
    } catch (AuthRefreshFailedException $exception) {
        expect($throw)->toBeTrue();
    }
    expect($exception)->toBeInstanceOf(UnauthorizedException::class)
        ->and($exception->response->body)->toBe('original')
        ->and($exception->response->headers['X-Origin'])->toBe(['main'])
        ->and($exception->dependencyResult->response->status)->toBe(match ($failure) {'http' => 503, 'empty' => 204, default => 200})
        ->and($exception->dependencyResult->isFailed())->toBeTrue()
        ->and(RefreshingAuthenticator::$refreshCalls)->toBe(1);
    $client->assertSent(ProtectedRequest::class, null, 1);
})->with([false, true])->with([false, true])->with(['http', 'empty', 'json']);

it('initial refresh возвращает фактическую ошибку зависимости', function (bool $throw): void {
    RefreshingAuthenticator::reset();
    RefreshingAuthenticator::$shouldRefresh = true;
    $transport = new MockTransport();
    $transport->fake([RefreshTokenRequest::class => MockResponse::make('dependency', 503)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', auth: new RefreshingAuthenticator(), throwOnErrors: $throw), $transport);
    try {
        $result = $client->send(new ProtectedRequest())->raw();
        expect($throw)->toBeFalse()
            ->and($result->errors->first()->code->value)->toBe('service_unavailable')
            ->and($result->response->body)->toBe('dependency');
    } catch (Throwable $exception) {
        expect($throw)->toBeTrue()->and($exception->response->status)->toBe(503);
    }
    $client->assertSent(ProtectedRequest::class, null, 0);
})->with([false, true]);

it('сохраняет ошибку refresh в batch и pool', function (bool $throw, string $mode): void {
    RefreshingAuthenticator::reset();
    $transport = new MockTransport();
    $transport->fake([
        ProtectedRequest::class => MockResponse::make('main', 401),
        RefreshTokenRequest::class => MockResponse::make('dependency', 503),
    ]);
    $factory = new RecordingFactory(fallback: true);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', auth: new RefreshingAuthenticator(), throwOnErrors: $throw, resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory)), $transport);
    $batch = $mode === 'pool'
        ? new PoolExecutor($client, [new ProtectedRequest()])
        : (new BatchExecutor($client, [new ProtectedRequest()]))->parallel();
    $result = ExecutionDelivery::result($batch->send(...), $throw, $factory)->results()->all()[0];
    expect($result->errors->first()->code->value)->toBe('unauthorized')
        ->and($result->errors->first()->context['reason'])->toBe('auth_refresh_failed')
        ->and($result->response->body)->toBe('main')
        ->and($result->exception->dependencyResult->response->body)->toBe('dependency');
    $client->assertSent(ProtectedRequest::class, null, 1);
})->with([false, true])->with(['parallel', 'pool']);
