<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Retry\RetryDelayCalculator;
use ApiSutra\Tests\Stubs\Retry\ProbeTransportDecorator;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Pipeline\Auth\AuthHandler;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\Pipeline\Hooks\HookRunner;
use ApiSutra\Pipeline\Transport\RetrySender;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

it('HTTP адаптер пересчитывает бюджет после ожиданий транспортного декоратора', function (int $wait): void {
    $clock = new VirtualClock();
    $http = new SequenceHttpClient([new Response(204)]);
    $factory = new HttpFactory();
    $transport = new HttpTransport($http, $factory, $factory);
    $decorator = new ProbeTransportDecorator($transport, onSend: static function () use ($clock, $wait): void {
        $clock->advance($wait);
    });
    $config = new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(totalTimeoutMs: 1000));
    $request = new RetryPolicyRequest();
    $context = new PipelineContext($request, $config, 'fixture-handler', preparedRequest: new PreparedRequest($request->getMethod(), 'https://fixture.test'));
    $context->budget = new ExecutionBudget($clock, 1000);
    $sender = new RetrySender(
        $config,
        $decorator,
        new RetryDelayCalculator(),
        new RateLimiter(),
        new HookRunner(new HookRegistry()),
        new AuthHandler($config),
        new ErrorPolicy(),
        new AuditLogger($config),
        sleeper: $clock
    );
    if ($wait < 1000) {
        expect($sender->sendWithRetry($request, $context)->status)->toBe(204)->and($http->options[0]->timeoutMs)->toBe(250);
    } else {
        expect(fn () => $sender->sendWithRetry($request, $context))->toThrow(ExecutionDeadlineException::class);
        expect($http->calls)->toBe(0);
    }
})->with([750, 1001]);
