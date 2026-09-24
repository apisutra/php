<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CooldownConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Pipeline\Auth\AuthHandler;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\Pipeline\Hooks\HookRunner;
use ApiSutra\Pipeline\Transport\CooldownResolver;
use ApiSutra\Pipeline\Transport\RetrySender;
use ApiSutra\RateLimiting\Cooldown\Backends\LocalCooldownBackend;
use ApiSutra\RateLimiting\RateLimitDecision;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\Retry\RetryDelayCalculator;
use ApiSutra\Retry\RetryAfterDelay;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Support\ScriptedRateLimitBackend;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

it('получает квоту после backoff и перепроверяет продлённый запрет без второго permit', function (): void {
    $clock = new VirtualClock();
    $registry = new LocalCooldownBackend($clock);
    $config = new ClientConfig(
        baseUrl: 'https://fixture.test',
        rateLimit: new RateLimitConfig(),
        retry: new RetryConfig(attempts: 2, baseDelay: 0, maxDelay: 0, jitter: false, totalTimeoutMs: 10_000)
    );
    $request = new RetryPolicyRequest();
    $context = new PipelineContext($request, $config, 'fixture', preparedRequest: new PreparedRequest($request->getMethod(), 'https://fixture.test'));
    $context->budget = new ExecutionBudget($clock, 10_000);
    $auth = new AuthHandler($config);
    $logger = new AuditLogger($config);
    $rule = (new CooldownResolver($config, $auth, $logger))->resolve($context);
    $quotaTimes = [];
    $backend = new ScriptedRateLimitBackend(static function (array $quotas, ?int $timeout, int $call) use ($clock, $registry, $rule, &$quotaTimes): RateLimitDecision {
        $quotaTimes[] = $clock->monotonicMs();
        if ($call === 2) {
            $registry->extend($rule->key, 2000);
        }
        return new RateLimitDecision(true);
    });
    $transport = new MockTransport();
    $httpTimes = [];
    $transport->fake(['*' => static function () use ($clock, &$httpTimes): MockResponse {
        $httpTimes[] = $clock->monotonicMs();
        return count($httpTimes) === 1 ? MockResponse::rateLimited(1) : MockResponse::success();
    }]);
    $sender = new RetrySender(
        $config,
        $transport,
        new RetryDelayCalculator(),
        new RateLimiter($clock, $clock, $backend),
        new HookRunner(new HookRegistry()),
        $auth,
        new ErrorPolicy(),
        $logger,
        sleeper: $clock,
        cooldownBackend: $registry
    );
    expect($sender->sendWithRetry($request, $context)->status)->toBe(200)
        ->and($quotaTimes)->toBe([1000, 2000])->and($httpTimes)->toBe([1000, 4000])
        ->and($backend->calls)->toBe(2)->and($clock->waits)->toBe([1000, 2000]);
});

it('использует один разбор даты и один момент ответа для retry и cooldown', function (): void {
    $clock = new VirtualClock();
    $config = new ClientConfig(
        baseUrl: 'https://fixture.test',
        retry: new RetryConfig(attempts: 2, baseDelay: 0, maxDelay: 0, jitter: false),
        cooldown: new CooldownConfig(maxAdditionalWaitMs: 0),
    );
    $request = new RetryPolicyRequest();
    $context = new PipelineContext($request, $config, 'fixture', preparedRequest: new PreparedRequest($request->getMethod(), 'https://fixture.test'));
    $context->budget = new ExecutionBudget($clock);
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::sequence([
        MockResponse::make('{}', 429, ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', $clock->unixTime() + 30)]),
        MockResponse::success(),
    ])]);
    $parser = new RetryAfterDelay(static function () use ($clock): int {
        $observed = $clock->unixTime();
        // Между чтениями календарное время сдвинулось, а обработка ответа заняла 250 ms.
        $clock->wallTime--;
        $clock->milliseconds += 250;
        return $observed;
    });
    $sender = new RetrySender(
        $config,
        $transport,
        new RetryDelayCalculator(),
        new RateLimiter($clock, $clock),
        new HookRunner(new HookRegistry()),
        new AuthHandler($config),
        new ErrorPolicy(),
        new AuditLogger($config),
        sleeper: $clock,
        retryAfterDelay: $parser,
        cooldownBackend: new LocalCooldownBackend($clock),
    );

    expect($sender->sendWithRetry($request, $context)->status)->toBe(200)
        ->and($clock->waits)->toBe([29_750])->and($transport->getRecorded())->toHaveCount(2);
});
