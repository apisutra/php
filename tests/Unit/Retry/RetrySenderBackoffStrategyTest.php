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
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\Retry\RetryDelayCalculator;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Tests\Support\VirtualClock;

describe('RetrySender backoff strategies', function () {
    it('использует стратегию backoff при повторных попытках', function (
        BackoffStrategy $strategy,
        array $expectedDelays,
    ) {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::serverError(),
                MockResponse::serverError(),
                MockResponse::success(['ok' => true]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            retry: new RetryConfig(
                attempts: 3,
                baseDelay: 100,
                maxDelay: 1000,
                backoff: $strategy,
                jitter: false,
                retryOn: [500],
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
        $context->budget = new ExecutionBudget($clock);
        $retrySender = new RetrySender(
            config: $config,
            transport: $transport,
            retryDelayPolicy: new RetryDelayCalculator(),
            sleeper: $clock,
            rateLimiter: new RateLimiter(),
            hookRunner: new HookRunner(new HookRegistry()),
            authHandler: new AuthHandler($config),
            errorPolicy: new ErrorPolicy(),
            auditLogger: new AuditLogger($config),
        );

        $response = $retrySender->sendWithRetry($request, $context);

        expect($response->status)->toBe(200);
        expect($clock->waits)->toBe($expectedDelays);
        expect($transport->getRecorded())->toHaveCount(3);
    })->with([
        'constant' => [BackoffStrategy::Constant, [100, 100]],
        'linear' => [BackoffStrategy::Linear, [100, 200]],
        'exponential' => [BackoffStrategy::Exponential, [100, 200]],
    ]);
});
