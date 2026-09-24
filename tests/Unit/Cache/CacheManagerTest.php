<?php

declare(strict_types=1);

use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\CacheableRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Transport\MockTransport;

describe('CacheManager', function () {
    it('возвращает ответ из кеша при повторном запросе', function () {
        $cacheStore = new ArrayCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            cacheConfig: new CacheConfig(store: $cacheStore, ttl: 60, prefix: 'tests'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $first = $request->send()->raw();
        $second = $request->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 1])
            ->and($transport->getRecorded())->toHaveCount(1);
    });
});
