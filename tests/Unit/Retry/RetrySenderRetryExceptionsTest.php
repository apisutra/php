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
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\Tests\Stubs\Retry\FlakyTransportException;
use ApiSutra\Tests\Stubs\Retry\FlakyTransport;

describe('RetrySender retryExceptions', function () {
    it('повторяет запрос при исключении из transport', function () {
        $transport = new FlakyTransport();

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            retry: new RetryConfig(
                attempts: 2,
                baseDelay: 0,
                maxDelay: 0,
                backoff: BackoffStrategy::Constant,
                jitter: false,
                retryOn: [],
                retryExceptions: [FlakyTransportException::class],
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

        $response = $retrySender->sendWithRetry($request, $context);

        expect($response->status)->toBe(200);
        expect($transport->calls)->toBe(2);
    });
});
