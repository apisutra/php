<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeRegistry;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Extensions\ExtensionRegistry;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Pipeline\Hydration\ResponseHydrator;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\Requests\ConfigPaginatedRequest;
use ApiSutra\Tests\Stubs\Requests\PaginatedItemsRequest;
use ApiSutra\Tests\Stubs\Requests\UnwrapResponseRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('ResponseHydrator', function () {
    it('извлекает элементы по itemsPath', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $hydrator = new ResponseHydrator(
            $config,
            new Hydrator(new CastRegistry()),
            new ExtensionRegistry(new CastRegistry(), new HookRegistry(), new AttributeRegistry()),
        );

        $request = new PaginatedItemsRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $data = ['data' => ['items' => [['id' => 1], ['id' => 2]]]];
        $result = $hydrator->hydrateResponse($request, $context, $data);

        expect($result)->toBe([['id' => 1], ['id' => 2]]);
    });

    it('unwrap и гидрирует DTO', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $hydrator = new ResponseHydrator(
            $config,
            new Hydrator(new CastRegistry()),
            new ExtensionRegistry(new CastRegistry(), new HookRegistry(), new AttributeRegistry()),
        );

        $request = new UnwrapResponseRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $data = ['data' => ['item' => ['id' => 7, 'name' => 'User']]];
        $result = $hydrator->hydrateResponse($request, $context, $data);

        expect($result)->toBeInstanceOf(SimpleResponseDto::class);
        expect($result->id)->toBe(7);
        expect($result->name)->toBe('User');
    });

    it('использует itemsPath из paginationConfig', function () {
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            paginationConfig: new PaginationConfig(itemsPath: 'response.items'),
            environment: Environment::Testing,
        );
        $hydrator = new ResponseHydrator(
            $config,
            new Hydrator(new CastRegistry()),
            new ExtensionRegistry(new CastRegistry(), new HookRegistry(), new AttributeRegistry()),
        );

        $request = new ConfigPaginatedRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $data = ['response' => ['items' => [['id' => 3], ['id' => 4]]]];
        $result = $hydrator->hydrateResponse($request, $context, $data);

        expect($result)->toBe([['id' => 3], ['id' => 4]]);
    });
});
