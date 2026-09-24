<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Pipeline\Transport\RateLimitApplier;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Tests\Stubs\Requests\RateLimitRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Support\SpyCache;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('RateLimitApplier', function () {
    it('пропускает rate limit при отключении в options', function () {
        $cache = new SpyCache();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            rateLimit: new RateLimitConfig(limit: 1, period: 60, behavior: RateLimitBehavior::Throw, store: $cache),
            environment: Environment::Testing,
        );

        $context = new PipelineContext(
            request: new SimpleGetRequest('q'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
            options: RequestOptions::empty()->withoutRateLimit(),
        );

        $applier = new RateLimitApplier($config, new RateLimiter());
        $applier->apply($context->request, $context);

        expect($cache->lastGetKey)->toBeNull();
        expect($cache->lastSetKey)->toBeNull();
    });

    it('использует атрибут и ограничивает запросы', function () {
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            rateLimit: new RateLimitConfig(limit: 100, period: 60, behavior: RateLimitBehavior::Throw),
            environment: Environment::Testing,
        );

        $request = new RateLimitRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $applier = new RateLimitApplier($config, new RateLimiter());
        $applier->apply($request, $context);

        expect(fn () => $applier->apply($request, $context))
            ->toThrow(RateLimitException::class);
    });
});
