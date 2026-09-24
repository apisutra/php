<?php

declare(strict_types=1);

use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\Base64UploadRequest;
use ApiSutra\Tests\Stubs\Requests\BinaryUploadRequest;
use ApiSutra\Tests\Stubs\Requests\MultipartUploadRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\SpyCache;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Files\FileInput;

describe('CacheManager file uploads', function () {
    it('не кеширует multipart upload', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            MultipartUploadRequest::class => MockResponse::sequence([
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
        $file = FileInput::fromContent('data', 'test.txt');
        $request = new MultipartUploadRequest([$file], 'note');
        $request->setClient($client);

        $first = $request->withCache()->send()->raw();
        $second = $request->withCache()->send()->raw();

        expect($first->errors->first()?->code->value)->toBe('configuration_error')
            ->and($second->errors->first()?->code->value)->toBe('configuration_error')
            ->and($transport->getRecorded())->toHaveCount(0)
            ->and($cache->lastSetKey)->toBeNull();
    });

    it('не кеширует base64 upload', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            Base64UploadRequest::class => MockResponse::sequence([
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
        $file = FileInput::fromContent('data', 'test.txt');
        $request = new Base64UploadRequest($file, 'note');
        $request->setClient($client);

        $first = $request->withCache()->send()->raw();
        $second = $request->withCache()->send()->raw();

        expect($first->errors->first()?->code->value)->toBe('configuration_error')
            ->and($second->errors->first()?->code->value)->toBe('configuration_error')
            ->and($transport->getRecorded())->toHaveCount(0)
            ->and($cache->lastSetKey)->toBeNull();
    });

    it('не кеширует binary upload', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            BinaryUploadRequest::class => MockResponse::sequence([
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
        $file = FileInput::fromContent('data', 'test.txt');
        $request = new BinaryUploadRequest($file);
        $request->setClient($client);

        $first = $request->withCache()->send()->raw();
        $second = $request->withCache()->send()->raw();

        expect($first->errors->first()?->code->value)->toBe('configuration_error')
            ->and($second->errors->first()?->code->value)->toBe('configuration_error')
            ->and($transport->getRecorded())->toHaveCount(0)
            ->and($cache->lastSetKey)->toBeNull();
    });
});
