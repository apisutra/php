<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Request\RequestPaginationHelper;
use ApiSutra\Tests\Stubs\Pagination\AttributeMetaResolver;
use ApiSutra\Tests\Stubs\Pagination\ConfigMetaResolver;
use ApiSutra\Tests\Stubs\Requests\AttributeMetaResolverRequest;
use ApiSutra\Tests\Stubs\Requests\ConfigPaginatedRequest;
use ApiSutra\Tests\Stubs\Requests\MethodMetaOverrideRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('Pagination meta resolver', function () {
    it('использует метод override с максимальным приоритетом', function () {
        AttributeMetaResolver::reset();
        ConfigMetaResolver::reset();
        MethodMetaOverrideRequest::reset();

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            paginationConfig: new PaginationConfig(metaResolver: ConfigMetaResolver::class),
            environment: Environment::Testing,
        );

        $request = new MethodMetaOverrideRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace-1',
            role: RequestRole::Root,
        );

        $helper = new RequestPaginationHelper($context, null);
        $meta = $helper->extractMeta($request, ['meta' => []]);

        expect($meta->total)->toBe(333);
        expect(MethodMetaOverrideRequest::$lastTraceId)->toBe('trace-1');
        expect(AttributeMetaResolver::$called)->toBeFalse();
        expect(ConfigMetaResolver::$called)->toBeFalse();
    });

    it('использует meta-resolver из атрибута', function () {
        AttributeMetaResolver::reset();
        ConfigMetaResolver::reset();

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            paginationConfig: new PaginationConfig(metaResolver: ConfigMetaResolver::class),
            environment: Environment::Testing,
        );

        $request = new AttributeMetaResolverRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace-2',
            role: RequestRole::Root,
        );

        $helper = new RequestPaginationHelper($context, null);
        $meta = $helper->extractMeta($request, ['meta' => []]);

        expect($meta->total)->toBe(111);
        expect(AttributeMetaResolver::$called)->toBeTrue();
        expect(ConfigMetaResolver::$called)->toBeFalse();
    });

    it('использует meta-resolver из конфига', function () {
        ConfigMetaResolver::reset();

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            paginationConfig: new PaginationConfig(metaResolver: ConfigMetaResolver::class),
            environment: Environment::Testing,
        );

        $request = new ConfigPaginatedRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace-3',
            role: RequestRole::Root,
        );

        $helper = new RequestPaginationHelper($context, null);
        $meta = $helper->extractMeta($request, ['meta' => []]);

        expect($meta->total)->toBe(222);
        expect(ConfigMetaResolver::$called)->toBeTrue();
    });

    it('использует дефолтный resolver и metaPath из defaults', function () {
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            paginationConfig: new PaginationConfig(metaPath: 'response.meta'),
            environment: Environment::Testing,
        );

        $request = new ConfigPaginatedRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace-4',
            role: RequestRole::Root,
        );

        $helper = new RequestPaginationHelper($context, null);
        $meta = $helper->extractMeta($request, [
            'response' => [
                'meta' => [
                    'page' => 2,
                    'per_page' => 5,
                    'total' => 9,
                    'has_more' => true,
                ],
            ],
        ]);

        expect($meta->currentPage)->toBe(2);
        expect($meta->perPage)->toBe(5);
        expect($meta->total)->toBe(9);
        expect($meta->hasMore)->toBeTrue();
    });
});
