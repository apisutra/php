<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Pipeline\Auth\AuthHandler;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\Pipeline\Hooks\HookRunner;
use ApiSutra\Pipeline\Transport\RetrySender;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\Retry\RetryDelayCalculator;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\RateLimiting\Cooldown\Backends\LocalCooldownBackend;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Support\FakeSleeper;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('RetrySender Retry-After', function () {
    it('повторяет запрос при 429 и заголовке Retry-After', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::rateLimited(2),
                MockResponse::success(['ok' => true]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            retry: new RetryConfig(
                attempts: 2,
                baseDelay: 0,
                maxDelay: 0,
                backoff: BackoffStrategy::Constant,
                jitter: false,
                retryOn: [429],
            ),
            environment: Environment::Testing,
        );

        $request = new SimpleGetRequest('q');
        $prepared = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/items',
            meta: [
                'requestClass' => SimpleGetRequest::class,
                'requestInstance' => $request,
            ],
        );

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            preparedRequest: $prepared,
        );

        $clock = new VirtualClock();
        $clock->wallTime = time();
        $context->budget = new ExecutionBudget($clock, $config->retry?->totalTimeoutMs);
        $sleeper = new FakeSleeper($clock);
        $retrySender = new RetrySender(
            config: $config,
            transport: $transport,
            retryDelayPolicy: new RetryDelayCalculator(),
            rateLimiter: new RateLimiter(),
            hookRunner: new HookRunner(new HookRegistry()),
            authHandler: new AuthHandler($config),
            errorPolicy: new ErrorPolicy(),
            auditLogger: new AuditLogger($config),
            sleeper: $sleeper,
            cooldownBackend: new LocalCooldownBackend($clock),
        );

        $response = $retrySender->sendWithRetry($request, $context);

        expect($response->status)->toBe(200);
        expect($transport->getRecorded())->toHaveCount(2);
        expect($sleeper->calls)->toBe(1);
        expect($sleeper->totalMs)->toBe(2000);
    });

    it('использует HTTP-date в Retry-After', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::make(
                    ['message' => 'Too Many'],
                    429,
                    ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 2)],
                ),
                MockResponse::success(['ok' => true]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            retry: new RetryConfig(
                attempts: 2,
                baseDelay: 0,
                maxDelay: 0,
                backoff: BackoffStrategy::Constant,
                jitter: false,
                retryOn: [429],
            ),
            environment: Environment::Testing,
        );

        $request = new SimpleGetRequest('q');
        $prepared = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/items',
            meta: [
                'requestClass' => SimpleGetRequest::class,
                'requestInstance' => $request,
            ],
        );

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            preparedRequest: $prepared,
        );

        $clock = new VirtualClock();
        $clock->wallTime = time();
        $context->budget = new ExecutionBudget($clock, $config->retry?->totalTimeoutMs);
        $sleeper = new FakeSleeper($clock);
        $retrySender = new RetrySender(
            config: $config,
            transport: $transport,
            retryDelayPolicy: new RetryDelayCalculator(),
            rateLimiter: new RateLimiter(),
            hookRunner: new HookRunner(new HookRegistry()),
            authHandler: new AuthHandler($config),
            errorPolicy: new ErrorPolicy(),
            auditLogger: new AuditLogger($config),
            sleeper: $sleeper,
            cooldownBackend: new LocalCooldownBackend($clock),
        );

        $response = $retrySender->sendWithRetry($request, $context);

        expect($response->status)->toBe(200);
        expect($transport->getRecorded())->toHaveCount(2);
        expect($sleeper->totalMs)->toBeGreaterThanOrEqual(1000);
        expect($sleeper->totalMs)->toBeLessThanOrEqual(3000);
    });

    it('повторяет 429 без Retry-After через backoff', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::make(['message' => 'Too Many'], 429),
                MockResponse::success(['ok' => true]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            retry: new RetryConfig(
                attempts: 2,
                baseDelay: 0,
                maxDelay: 0,
                backoff: BackoffStrategy::Constant,
                jitter: false,
                retryOn: [429],
            ),
            environment: Environment::Testing,
        );

        $request = new SimpleGetRequest('q');
        $prepared = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/items',
            meta: [
                'requestClass' => SimpleGetRequest::class,
                'requestInstance' => $request,
            ],
        );

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            preparedRequest: $prepared,
        );

        $clock = new VirtualClock();
        $clock->wallTime = time();
        $context->budget = new ExecutionBudget($clock, $config->retry?->totalTimeoutMs);
        $sleeper = new FakeSleeper($clock);
        $retrySender = new RetrySender(
            config: $config,
            transport: $transport,
            retryDelayPolicy: new RetryDelayCalculator(),
            rateLimiter: new RateLimiter(),
            hookRunner: new HookRunner(new HookRegistry()),
            authHandler: new AuthHandler($config),
            errorPolicy: new ErrorPolicy(),
            auditLogger: new AuditLogger($config),
            sleeper: $sleeper,
            cooldownBackend: new LocalCooldownBackend($clock),
        );

        $response = $retrySender->sendWithRetry($request, $context);

        expect($response->status)->toBe(200);
        expect($transport->getRecorded())->toHaveCount(2);
        expect($sleeper->calls)->toBe(0);
    });
});
