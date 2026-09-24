<?php

declare(strict_types=1);

use ApiSutra\Enums\Cache\CacheMode;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Enums\Pagination\PaginationMode;
use ApiSutra\Enums\Request\CredentialsMergeMode;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Tests\Stubs\Requests\PaginatedRequest;

describe('RequestExecution цепочка опций', function () {
    it('withCache затем withPage сохраняет обе опции', function () {
        $request = new PaginatedRequest();

        $execution = $request
            ->withCache(120)
            ->withPage(2)
            ->withLimit(25);

        $cacheOverride = $execution->getOptions()->getCacheOverride();
        $paginationOptions = $execution->getPaginationOptions();

        expect($cacheOverride->mode)->toBe(CacheMode::Enabled);
        expect($cacheOverride->ttl)->toBe(120);
        expect($paginationOptions->hasPage())->toBeTrue();
        expect($paginationOptions->getPage())->toBe(2);
        expect($paginationOptions->hasLimit())->toBeTrue();
        expect($paginationOptions->getLimit())->toBe(25);
    });

    it('withPage затем withCache сохраняет пагинацию', function () {
        $request = new PaginatedRequest();

        $execution = $request
            ->withPage(3)
            ->withCache();

        $cacheOverride = $execution->getOptions()->getCacheOverride();
        $paginationOptions = $execution->getPaginationOptions();

        expect($cacheOverride->mode)->toBe(CacheMode::Enabled);
        expect($cacheOverride->ttl)->toBeNull();
        expect($paginationOptions->hasPage())->toBeTrue();
        expect($paginationOptions->getPage())->toBe(3);
    });

    it('withCache затем withoutCache отключает кеш', function () {
        $request = new PaginatedRequest();

        $execution = $request
            ->withCache(120)
            ->withoutCache();

        $cacheOverride = $execution->getOptions()->getCacheOverride();

        expect($cacheOverride->mode)->toBe(CacheMode::Disabled);
        expect($cacheOverride->ttl)->toBe(120);
    });

    it('withoutCache затем withCache включает кеш', function () {
        $request = new PaginatedRequest();

        $execution = $request
            ->withoutCache()
            ->withCache();

        $cacheOverride = $execution->getOptions()->getCacheOverride();

        expect($cacheOverride->mode)->toBe(CacheMode::Enabled);
        expect($cacheOverride->ttl)->toBeNull();
    });

    it('withCacheWriteOnly задаёт write-only режим кеша', function () {
        $request = new PaginatedRequest();

        $execution = $request
            ->withCacheWriteOnly(120)
            ->withPage(1);

        $cacheOverride = $execution->getOptions()->getCacheOverride();

        expect($cacheOverride->mode)->toBe(CacheMode::WriteOnly);
        expect($cacheOverride->ttl)->toBe(120);
    });

    it('rules задаёт override правила пагинации', function () {
        $request = new PaginatedRequest();

        $execution = $request->rules(PaginationRule::pages(2));

        $rule = $execution->getOptions()->getPaginationRuleOverride();

        expect($rule)->not->toBeNull();
        expect($rule?->mode)->toBe(PaginationMode::Pages);
        expect($rule?->pages)->toBe(2);
    });

    it('withCacheReadOnly задаёт read-only режим кеша', function () {
        $request = new PaginatedRequest();

        $execution = $request
            ->withCacheReadOnly(60)
            ->withPage(1);

        $cacheOverride = $execution->getOptions()->getCacheOverride();

        expect($cacheOverride->mode)->toBe(CacheMode::ReadOnly);
        expect($cacheOverride->ttl)->toBe(60);
    });

    it('цепочка credentials override опций сохраняется в execution', function () {
        $request = new PaginatedRequest();

        $execution = $request
            ->withoutCredentialsEnrichment()
            ->withCredentialsMergeMode(CredentialsMergeMode::Overwrite)
            ->withCredentialsScope('system');

        expect($execution->getOptions()->getCredentialsEnrichmentEnabledOverride())->toBeFalse()
            ->and($execution->getOptions()->getCredentialsMergeModeOverride())->toBe(CredentialsMergeMode::Overwrite)
            ->and($execution->getOptions()->getCredentialsScopeOverride())->toBe('system');
    });

    it('asProvider* корректно выставляет continuation mode override', function () {
        $request = new PaginatedRequest();

        $sync = $request->asProviderSync();
        $async = $request->asProviderAsync();
        $auto = $request->asProviderAuto();

        expect($sync->getOptions()->getContinuationModeOverride())->toBe(ContinuationMode::Sync)
            ->and($async->getOptions()->getContinuationModeOverride())->toBe(ContinuationMode::Async)
            ->and($auto->getOptions()->getContinuationModeOverride())->toBe(ContinuationMode::Auto);
    });
});
