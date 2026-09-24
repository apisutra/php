<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Retry\RetryDelayCalculator;
use ApiSutra\Tests\Stubs\Retry\ProbeTransportDecorator;
use ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Http\RequestDestination;
use ApiSutra\Pipeline\Auth\AuthHandler;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\Pipeline\Hooks\HookRunner;
use ApiSutra\Pipeline\Transport\RetrySender;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;

it('проверяет фактический адрес после транспортного декоратора', function (): void {
    $transport = new MockTransport();
    $decorator = new ProbeTransportDecorator($transport, map: static fn (PreparedRequest $request): PreparedRequest => $request->with(url: 'https://other.test/changed'));
    $config = new ClientConfig(baseUrl: 'https://api.test', retry: new RetryConfig());
    $request = new CacheProbeRequest();
    $destination = new RequestDestination('https://storage.test/file?sig=fixture', $config->baseUrl, true);
    $context = new PipelineContext($request, $config, 'fixture-target', preparedRequest: new PreparedRequest($request->getMethod(), $destination->url, destination: $destination));
    $context->destination = $destination;
    $sender = new RetrySender(
        $config,
        $decorator,
        new RetryDelayCalculator(),
        new RateLimiter(),
        new HookRunner(new HookRegistry()),
        new AuthHandler($config),
        new ErrorPolicy(),
        new AuditLogger($config)
    );
    expect(fn () => $sender->sendWithRetry($request, $context))->toThrow(ConfigurationException::class);
    expect($transport->getRecorded())->toBe([]);
});
