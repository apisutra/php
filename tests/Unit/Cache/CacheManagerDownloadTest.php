<?php

declare(strict_types=1);

use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use ApiSutra\Tests\Stubs\Requests\DownloadCacheRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\SpyCache;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Files\FileResponse;

describe('CacheManager download', function () {
    it('не кеширует download по умолчанию', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            ProviderBDownloadRequest::class => MockResponse::sequence([
                MockResponse::make('file-1', 200, ['Content-Type' => 'application/octet-stream']),
                MockResponse::make('file-2', 200, ['Content-Type' => 'application/octet-stream']),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheConfig: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new ProviderBDownloadRequest('op-1');
        $request->setClient($client);

        $first = $request->send()->raw();
        $second = $request->send()->raw();

        expect($first->data)->toBeInstanceOf(FileResponse::class)
            ->and($second->data)->toBeInstanceOf(FileResponse::class)
            ->and($transport->getRecorded())->toHaveCount(2)
            ->and($cache->lastSetKey)->toBeNull();
    });

    it('отклоняет кеш download при withCache()', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            ProviderBDownloadRequest::class => MockResponse::sequence([
                MockResponse::make('file-1', 200, ['Content-Type' => 'application/octet-stream']),
                MockResponse::make('file-2', 200, ['Content-Type' => 'application/octet-stream']),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheConfig: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new ProviderBDownloadRequest('op-1');
        $request->setClient($client);

        $first = $request->withCache()->send()->raw();
        $second = $request->withCache()->send()->raw();

        expect($first->errors->first()?->code->value)->toBe('configuration_error')
            ->and($second->errors->first()?->code->value)->toBe('configuration_error')
            ->and($transport->getRecorded())->toHaveCount(0)
            ->and($cache->lastSetKey)->toBeNull();
    });

    it('отклоняет кеш download при #[Cache]', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            DownloadCacheRequest::class => MockResponse::sequence([
                MockResponse::make('file-1', 200, ['Content-Type' => 'application/octet-stream']),
                MockResponse::make('file-2', 200, ['Content-Type' => 'application/octet-stream']),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cacheConfig: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new DownloadCacheRequest();
        $request->setClient($client);

        $first = $request->send()->raw();
        $second = $request->send()->raw();

        expect($first->errors->first()?->code->value)->toBe('configuration_error')
            ->and($second->errors->first()?->code->value)->toBe('configuration_error')
            ->and($transport->getRecorded())->toHaveCount(0)
            ->and($cache->lastSetKey)->toBeNull();
    });
});
