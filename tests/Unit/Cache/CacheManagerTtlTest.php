<?php

declare(strict_types=1);

use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\CacheableRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\ControllableTimeCache;
use ApiSutra\Transport\MockTransport;

describe('CacheManager TTL', function () {
    it('игнорирует просроченный кеш', function () {
        $cache = new ControllableTimeCache(1_000_000);
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::sequence([
                MockResponse::success(['value' => 1]),
                MockResponse::success(['value' => 2]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheConfig: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $execution = $request->withCache(1);
        $first = $execution->send()->raw();

        $cache->advance(2);

        $second = $execution->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 2])
            ->and($transport->getRecorded())->toHaveCount(2);
    });
});
