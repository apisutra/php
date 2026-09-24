<?php

declare(strict_types=1);

use Acme\Discovery\FlowClient;
use Acme\Discovery\Requests\DiscoveryRequest;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\ClientDiscoveryCache;
use ApiSutra\Resolver\ClientDiscoveryService;
use ApiSutra\Resolver\ClientRegistry;
use ApiSutra\Resolver\ClientResolver;
use ApiSutra\Resolver\DiscoveryOptions;
use ApiSutra\Resolver\RequestNamespaceDetector;
use ApiSutra\Resolver\RequestScanner;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;

describe('Discovery full flow', function () {
    it('проходит discovery → pipeline → execution → result', function () {
        $transport = new MockTransport();
        $transport->fake([
            DiscoveryRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new FlowClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache();
        $service = new ClientDiscoveryService($registry, $detector, $cache);

        $service->registerAuto($client, DiscoveryOptions::forceOff());

        $resolver = new ClientResolver($registry);
        $request = new DiscoveryRequest();
        $request->setClient($resolver->resolve($request));

        $result = $request->send()->raw();

        expect($result->isSuccess())->toBeTrue();
        expect($result->data)->toBe(['ok' => true]);
    });
});
