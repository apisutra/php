<?php

declare(strict_types=1);

use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Exceptions\Auth\AuthRefreshLockTimeoutException;
use ApiSutra\Exceptions\Auth\AuthDependencyException;
use ApiSutra\Pipeline\Auth\AuthHandler;
use ApiSutra\Tests\Stubs\Auth\LockAwareAuthenticator;
use ApiSutra\Tests\Stubs\Dto\TokenResponseDto;
use ApiSutra\Tests\Stubs\Requests\AuthRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\FakeSleeper;
use ApiSutra\Tests\Support\RecordingClientExecutor;
use ApiSutra\Tests\Support\TestAuthLockProvider;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('AuthHandler refresh lock', function () {
    it('не делает refresh при занятых lock и отсутствии потребности', function () {
        $locks = new TestAuthLockProvider();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-1', stopAfterFirstCheck: true);
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cacheConfig: new CacheConfig(locks: $locks),
            environment: Environment::Testing,
        );

        $executor = new RecordingClientExecutor(new TokenResponseDto('new-token'));
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $sleeper);

        $transport = new MockTransport();
        $client = new TestClient($config, $transport);

        $request = new AuthRequest('payload');
        $request->setClient($client);

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace-1',
            preparedRequest: new PreparedRequest(HttpMethod::GET, 'https://api.test/auth'),
        );

        $locks->busy = true;

        $context->executor = $executor;
        $handler->handleAuthentication($request, $context);

        expect($locks->keys)->toBe([])
            ->and($auth->refreshCalls)->toBe(0)
            ->and($executor->calls)->toBe(0);
    });

    it('освобождает lock после успешного refresh', function () {
        $locks = new TestAuthLockProvider();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-2');
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cacheConfig: new CacheConfig(locks: $locks),
            environment: Environment::Testing,
        );

        $executor = new RecordingClientExecutor(new TokenResponseDto('new-token'));
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $sleeper);

        $transport = new MockTransport();
        $client = new TestClient($config, $transport);

        $request = new AuthRequest('payload');
        $request->setClient($client);

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace-2',
            preparedRequest: new PreparedRequest(HttpMethod::GET, 'https://api.test/auth'),
        );

        $context->executor = $executor;
        $handler->handleAuthentication($request, $context);

        expect($locks->keys)->toHaveCount(1)
            ->and($locks->releases)->toBe(1)
            ->and($auth->refreshCalls)->toBe(1)
            ->and($executor->calls)->toBe(1);
    });

    it('ждет lock до тайм-аута и прекращает авторизацию', function () {
        $locks = new TestAuthLockProvider();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-timeout');
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cacheConfig: new CacheConfig(locks: $locks),
            environment: Environment::Testing,
        );

        $executor = new RecordingClientExecutor(new TokenResponseDto('new-token'));
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $sleeper);

        $locks->busy = true;

        $request = new AuthRequest('payload');
        $context = new PipelineContext($request, $config, 'trace-lock');
        expect(fn () => $handler->handleAuthentication($request, $context))->toThrow(AuthRefreshLockTimeoutException::class);

        expect($executor->calls)->toBe(0)
            ->and($sleeper->calls)->toBeGreaterThan(0)
            ->and($sleeper->totalMs)->toBeGreaterThanOrEqual(1000);
    });

    it('force refresh не проверяет shouldRefresh при ожидании лока', function () {
        $locks = new TestAuthLockProvider();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-force-wait');
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cacheConfig: new CacheConfig(locks: $locks),
            environment: Environment::Testing,
        );

        $executor = new RecordingClientExecutor(new TokenResponseDto('new-token'));
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $sleeper);

        $locks->busy = true;

        $request = new AuthRequest('payload');
        $context = new PipelineContext($request, $config, 'trace-lock');
        expect(fn () => $handler->handleAuthentication($request, $context, true))->toThrow(AuthRefreshLockTimeoutException::class);

        expect($auth->shouldRefreshCalls)->toBe(0)
            ->and($sleeper->calls)->toBeGreaterThan(0)
            ->and($executor->calls)->toBe(0);
    });

    it('force refresh выполняет refresh даже при shouldRefresh=false', function () {
        $locks = new TestAuthLockProvider();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-force');
        $auth->processTokenResponse(new TokenResponseDto('current-token'));
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cacheConfig: new CacheConfig(locks: $locks),
            environment: Environment::Testing,
        );

        $executor = new RecordingClientExecutor(new TokenResponseDto('new-token'));
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $sleeper);

        $transport = new MockTransport();
        $client = new TestClient($config, $transport);

        $request = new AuthRequest('payload');
        $request->setClient($client);

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace-force',
            preparedRequest: new PreparedRequest(HttpMethod::GET, 'https://api.test/auth'),
        );

        $context->executor = $executor;
        $handler->handleAuthentication($request, $context, true);

        expect($auth->shouldRefreshCalls)->toBe(0)
            ->and($auth->refreshCalls)->toBe(1)
            ->and($executor->calls)->toBe(1);
    });

    it('выбрасывает UnauthorizedException при неуспешном refresh', function () {
        $locks = new TestAuthLockProvider();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-fail');
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            authRetryAttempts: 1,
            cacheConfig: new CacheConfig(locks: $locks),
            environment: Environment::Testing,
        );

        $executor = new RecordingClientExecutor(status: ResultStatus::FAILED);
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $sleeper);

        $transport = new MockTransport();
        $client = new TestClient($config, $transport);

        $request = new AuthRequest('payload');
        $request->setClient($client);

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace-fail',
            preparedRequest: new PreparedRequest(HttpMethod::GET, 'https://api.test/auth'),
        );

        $context->executor = $executor;
        expect(fn() => $handler->handleAuthentication($request, $context))
            ->toThrow(AuthDependencyException::class)
            ->and($executor->calls)->toBe(1);
    });
});
