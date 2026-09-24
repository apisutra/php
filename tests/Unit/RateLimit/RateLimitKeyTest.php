<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Pipeline\Transport\RateLimitApplier;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Tests\Stubs\Requests\RateLimitKeyRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Support\SpyCache;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('Rate limit key', function () {
    function buildRateLimitKey(string $rawKey): string
    {
        return hash('sha256', 'apisutra.rate-limit.v2:' . $rawKey);
    }

    it('стабилен для одного baseUrl', function () {
        $cache = new SpyCache();
        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            rateLimit: new RateLimitConfig(limit: 2, period: 60, store: $cache),
            environment: Environment::Testing,
        );

        $request = new SimpleGetRequest('q');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $applier = new RateLimitApplier($config, new RateLimiter());
        $applier->apply($request, $context);
        $firstKey = $cache->lastSetKey ?? $cache->lastGetKey;

        $applier->apply($request, $context);
        $secondKey = $cache->lastSetKey ?? $cache->lastGetKey;

        expect($firstKey)->not->toBeNull();
        expect($secondKey)->toBe($firstKey);
    });

    it('разный для разных baseUrl', function () {
        $cacheA = new SpyCache();
        $configA = new ClientConfig(
            baseUrl: 'https://provider-a.test',
            rateLimit: new RateLimitConfig(limit: 2, period: 60, store: $cacheA),
            environment: Environment::Testing,
        );

        $cacheB = new SpyCache();
        $configB = new ClientConfig(
            baseUrl: 'https://provider-b.test',
            rateLimit: new RateLimitConfig(limit: 2, period: 60, store: $cacheB),
            environment: Environment::Testing,
        );

        $requestA = new SimpleGetRequest('q');
        $contextA = new PipelineContext(
            request: $requestA,
            config: $configA,
            traceId: 'trace-a',
            role: RequestRole::Root,
        );

        $requestB = new SimpleGetRequest('q');
        $contextB = new PipelineContext(
            request: $requestB,
            config: $configB,
            traceId: 'trace-b',
            role: RequestRole::Root,
        );

        (new RateLimitApplier($configA, new RateLimiter()))->apply($requestA, $contextA);
        (new RateLimitApplier($configB, new RateLimiter()))->apply($requestB, $contextB);

        $keyA = $cacheA->lastSetKey ?? $cacheA->lastGetKey;
        $keyB = $cacheB->lastSetKey ?? $cacheB->lastGetKey;

        expect($keyA)->not->toBeNull();
        expect($keyB)->not->toBeNull();
        expect($keyA)->not->toBe($keyB);
    });

    it('использует кастомный ключ из override', function () {
        $cache = new SpyCache();
        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            rateLimit: new RateLimitConfig(limit: 2, period: 60, store: $cache),
            environment: Environment::Testing,
            includeClientQuota: false,
        );

        $request = new SimpleGetRequest('q');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
            options: RequestOptions::empty()->withRateLimit(2, 60, key: 'custom-key'),
        );

        $applier = new RateLimitApplier($config, new RateLimiter());
        $applier->apply($request, $context);

        $key = $cache->lastSetKey ?? $cache->lastGetKey;
        expect($key)->toBe(buildRateLimitKey('custom-key'));
    });

    it('использует ключ из атрибута при отсутствии override', function () {
        $cache = new SpyCache();
        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            rateLimit: new RateLimitConfig(limit: 2, period: 60, store: $cache),
            environment: Environment::Testing,
            includeClientQuota: false,
        );

        $request = new RateLimitKeyRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $applier = new RateLimitApplier($config, new RateLimiter());
        $applier->apply($request, $context);

        $key = $cache->lastSetKey ?? $cache->lastGetKey;
        expect($key)->toBe(buildRateLimitKey('attr-key'));
    });

    it('override имеет приоритет над атрибутом', function () {
        $cache = new SpyCache();
        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            rateLimit: new RateLimitConfig(limit: 2, period: 60, store: $cache),
            environment: Environment::Testing,
            includeClientQuota: false,
        );

        $request = new RateLimitKeyRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
            options: RequestOptions::empty()->withRateLimit(2, 60, key: 'override-key'),
        );

        $applier = new RateLimitApplier($config, new RateLimiter());
        $applier->apply($request, $context);

        $key = $cache->lastSetKey ?? $cache->lastGetKey;
        expect($key)->toBe(buildRateLimitKey('override-key'));
    });
});
