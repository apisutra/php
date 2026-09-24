<?php

declare(strict_types=1);

use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Cache\CacheMode;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\AttributeRichRequest;
use ApiSutra\Tests\Stubs\Requests\CacheableRequest;
use ApiSutra\Tests\Stubs\Requests\CacheDisabledRequest;
use ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\SpyCache;
use ApiSutra\Transport\MockTransport;

describe('CacheManager overrides', function () {
    it('withoutCache отключает чтение и запись', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::sequence([
                MockResponse::make(['value' => 1]),
                MockResponse::make(['value' => 2]),
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

        $first = $request->withoutCache()->send()->raw();
        $second = $request->withoutCache()->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 2])
            ->and($transport->getRecorded())->toHaveCount(2)
            ->and($cache->lastSetKey)->toBeNull();
    });

    it('withCacheWriteOnly пишет в кеш без чтения', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::sequence([
                MockResponse::make(['value' => 1]),
                MockResponse::make(['value' => 2]),
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

        $first = $request->withCacheWriteOnly()->send()->raw();
        $second = $request->withCacheWriteOnly()->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 2])
            ->and($transport->getRecorded())->toHaveCount(2)
            ->and($cache->lastSetKey)->not->toBeNull();
    });

    it('withCache применяет ttl override', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheConfig: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $request->withCache(15)->send()->raw();

        expect($cache->lastSetTtl)->toBe(15);
    });

    it('withCache использует ttl из атрибута при null override', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheConfig: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $request->withCache()->send()->raw();

        expect($cache->lastSetTtl)->toBe(120);
    });

    it('withCache использует ttl из конфига при отсутствии атрибута', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'User']),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheConfig: new CacheConfig(store: $cache, ttl: 45, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $request->withCache()->send()->raw();

        expect($cache->lastSetTtl)->toBe(45);
    });

    it('атрибут Cache(mode=Disabled) отключает кеш', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheDisabledRequest::class => MockResponse::sequence([
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
        $request = new CacheDisabledRequest('payload');
        $request->setClient($client);

        $first = $request->send()->raw();
        $second = $request->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 2])
            ->and($transport->getRecorded())->toHaveCount(2)
            ->and($cache->lastSetKey)->toBeNull();
    });

    it('withCache включает кеш при Cache(mode=Disabled)', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheDisabledRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheConfig: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheDisabledRequest('payload');
        $request->setClient($client);

        $first = $request->withCache()->send()->raw();
        $second = $request->withCache()->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 1])
            ->and($transport->getRecorded())->toHaveCount(1)
            ->and($cache->lastSetKey)->not->toBeNull();
    });

    it('withCache включает кеш при глобальном отключении', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheConfig: new CacheConfig(store: $cache, ttl: 60, mode: CacheMode::Disabled, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $first = $request->withCache()->send()->raw();
        $second = $request->withCache()->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 1])
            ->and($transport->getRecorded())->toHaveCount(1);
    });

    it('baseUrl override влияет на cache key', function () {
        $cache = new SpyCache();
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

        $first = $request->withCache()->send()->raw();
        $firstKey = $cache->lastSetKey;

        $second = $request->withCache()->withBaseUrl('https://api.alt')->send()->raw();
        $secondKey = $cache->lastSetKey;

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 2])
            ->and($transport->getRecorded())->toHaveCount(2)
            ->and($firstKey)->not->toBe($secondKey);
    });

    it('использует key из cache-атрибута', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            AttributeRichRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheConfig: new CacheConfig(store: $cache, ttl: 60, prefix: 'prefix:'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new AttributeRichRequest('payload');
        $request->setClient($client);

        $request->withIdempotencyKey('id')->withCache()->send()->raw();

        expect($cache->lastSetKey)->toMatch('/^[a-f0-9]{64}$/');
        $request->withIdempotencyKey('other-id')->withCache()->send()->raw();
        expect($transport->getRecorded())->toHaveCount(1);
    });

    it('page и limit влияют на cache key', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::success(['data' => []]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheConfig: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);
        $request = new PaginatedRequest();
        $request->setClient($client);

        $request->withCache(300)->withPage(1)->withLimit(10)->send()->raw();
        $firstKey = $cache->lastSetKey;

        $request->withCache(300)->withPage(2)->withLimit(10)->send()->raw();
        $secondKey = $cache->lastSetKey;

        expect($firstKey)->not->toBeNull()
            ->and($secondKey)->not->toBeNull()
            ->and($firstKey)->not->toBe($secondKey);
    });

    it('clearCache инвалидирует запись запроса', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheConfig: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $request->withCache()->send()->raw();
        $setKey = $cache->lastSetKey;

        $request->clearCache();

        expect($setKey)->not->toBeNull();
        $request->withCache()->send()->raw();
        expect($transport->getRecorded())->toHaveCount(2);
    });

    it('clearCache учитывает pagination options', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::success(['data' => []]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheConfig: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);
        $request = new PaginatedRequest();
        $request->setClient($client);

        $execution = $request->withCache(300)->withPage(2)->withLimit(10);
        $execution->send()->raw();
        $setKey = $cache->lastSetKey;

        $execution->clearCache();

        expect($setKey)->not->toBeNull();
        $execution->send()->raw();
        expect($transport->getRecorded())->toHaveCount(2);
    });
});
