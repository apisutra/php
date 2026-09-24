<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Retry\RetryDelayCalculator;
use ApiSutra\Testing\MockResponse;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Pipeline\Auth\AuthHandler;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\Pipeline\Hooks\HookRunner;
use ApiSutra\Pipeline\Transport\RetrySender;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('RetrySender total timeout', function () {
    it('останавливает повторы при превышении общего таймаута', function () {
        $transport = new MockTransport();

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            retry: new RetryConfig(
                attempts: 2,
                baseDelay: 0,
                maxDelay: 0,
                backoff: BackoffStrategy::Constant,
                jitter: false,
                retryOn: [500],
                totalTimeoutMs: 1,
            ),
            environment: Environment::Testing,
        );

        $request = new SimpleGetRequest('q');
        $prepared = new PreparedRequest(
            method: $request->getMethod(),
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
        $context->budget = new ExecutionBudget($clock, 1);
        $transport->fake(['*' => static function () use ($clock): MockResponse {
            $clock->advance(2);
            return MockResponse::serverError();
        }]);

        $retrySender = new RetrySender(
            config: $config,
            transport: $transport,
            retryDelayPolicy: new RetryDelayCalculator(),
            rateLimiter: new RateLimiter(),
            hookRunner: new HookRunner(new HookRegistry()),
            authHandler: new AuthHandler($config),
            errorPolicy: new ErrorPolicy(),
            auditLogger: new AuditLogger($config),
        );

        expect(fn () => $retrySender->sendWithRetry($request, $context))
            ->toThrow(ExecutionDeadlineException::class, 'Execution budget exhausted');

        expect($transport->getRecorded())->toHaveCount(1);
    });
});
