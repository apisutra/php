<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Inventory\OperationInventoryInterface;
use ApiSutra\OperationInventory\OperationInventoryBuilder;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\RequestScanner;
use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Tests\Stubs\Inventory\InventoryCallPathClient;
use ApiSutra\Tests\Stubs\Inventory\Requests\InventoryListOrdersRequest;
use ApiSutra\Tests\Stubs\Inventory\Requests\InventoryOrderDetailsRequest;
use ApiSutra\Tests\Stubs\Inventory\Requests\InventoryStatusRequest;
use ApiSutra\Tests\Stubs\Inventory\Requests\InventoryV1ReportRequest;
use ApiSutra\Tests\Stubs\Inventory\Requests\InventoryV2ReportRequest;
use ApiSutra\Tests\Stubs\Requests\DownloadCacheRequest;
use ApiSutra\Tests\Stubs\Requests\NoAuthRequest;
use ApiSutra\Tests\Stubs\Requests\OperationDescriptorRequest;
use ApiSutra\Tests\Stubs\Requests\OverrideEndpointRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\Requests\SkipCredentialsRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('OperationInventoryBuilder', function () {
    it('строит inventory из classmap и резолвит spec flags', function () {
        $builder = new OperationInventoryBuilder(
            new RequestScanner(new ClassMapProvider()),
            new RequestSpecResolver(),
        );

        $inventory = $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs');

        expect($inventory)->toBeInstanceOf(OperationInventoryInterface::class);
        expect($inventory->forRequest(SimpleGetRequest::class))->not->toBeNull()
            ->and($inventory->forRequest(SimpleGetRequest::class)?->sdkCallPaths)->toBe([]);

        $noAuth = $inventory->forRequest(NoAuthRequest::class);
        expect($noAuth?->hasNoAuth)->toBeTrue();

        $skip = $inventory->forRequest(SkipCredentialsRequest::class);
        expect($skip?->skipCredentialsEnrichment)->toBeTrue();

        $download = $inventory->forRequest(DownloadCacheRequest::class);
        expect($download?->hasDownload)->toBeTrue();
    });

    it('строит inventory через PSR-4 fallback при пустом classmap', function () {
        $builder = new OperationInventoryBuilder(
            new RequestScanner(new ClassMapProvider(
                classMapOverride: [],
                psr4PrefixesOverride: [
                    'ApiSutra\\Tests\\Stubs\\' => [
                        dirname(__DIR__, 2) . '/Stubs',
                    ],
                ],
            )),
            new RequestSpecResolver(),
        );

        $inventory = $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs');

        expect($inventory->forRequest(SimpleGetRequest::class))->not->toBeNull()
            ->and($inventory->forRequest(OperationDescriptorRequest::class)?->title())->toBe('Operation title')
            ->and($inventory->forRequest(OperationDescriptorRequest::class)?->description())->toBe('Operation description')
            ->and($inventory->forRequest(OperationDescriptorRequest::class)?->note())->toBe('Operation note');
    });

    it('ищет операции по endpoint и использует declarative endpoint вместо runtime override', function () {
        $builder = new OperationInventoryBuilder(
            new RequestScanner(new ClassMapProvider()),
            new RequestSpecResolver(),
        );

        $inventory = $builder->buildForClient(TestClient::class);

        expect($inventory->forRequest(OverrideEndpointRequest::class)?->endpoint)->toBe('/declared')
            ->and(array_map(
                static fn ($item) => $item->requestClass,
                $inventory->forEndpoint('/simple'),
            ))->toContain(SimpleGetRequest::class);
    });

    it('резолвит стабильные sdkCallPaths для прямых, alias и version paths', function () {
        $builder = new OperationInventoryBuilder(
            new RequestScanner(new ClassMapProvider()),
            new RequestSpecResolver(),
        );

        $inventory = $builder->buildForClient(InventoryCallPathClient::class);

        expect($inventory->forRequest(InventoryStatusRequest::class)?->sdkCallPaths)->toBe([
            'status()',
        ]);

        expect($inventory->forRequest(InventoryListOrdersRequest::class)?->sdkCallPaths)->toBe([
            'orders()->listAll()',
            'v1()->orders()->listAll()',
            'v2()->orders()->listAll()',
        ]);

        expect($inventory->forRequest(InventoryOrderDetailsRequest::class)?->sdkCallPaths)->toBe([
            'orders()->download()',
            'orders()->get()',
            'v1()->orders()->download()',
            'v1()->orders()->get()',
            'v2()->orders()->download()',
            'v2()->orders()->get()',
        ]);
    });

    it('резолвит version router и versioned resource paths без parameterized selectors', function () {
        $builder = new OperationInventoryBuilder(
            new RequestScanner(new ClassMapProvider()),
            new RequestSpecResolver(),
        );

        $inventory = $builder->buildForClient(InventoryCallPathClient::class);

        expect($inventory->forRequest(InventoryV1ReportRequest::class)?->sdkCallPaths)->toBe([
            'reports()->v1()->fetch()',
            'v1()->reports()->fetch()',
        ]);

        expect($inventory->forRequest(InventoryV2ReportRequest::class)?->sdkCallPaths)->toBe([
            'reports()->v2()->fetch()',
            'v2()->reports()->fetch()',
        ]);

        expect($inventory->forRequest(InventoryV1ReportRequest::class)?->sdkCallPaths)
            ->not->toContain('reports()->fetch()')
            ->not->toContain('useVersion()->reports()->fetch()')
            ->not->toContain('reports()->useVersion()->fetch()');
    });

    it('возвращает пустые sdkCallPaths при buildForRootNamespace без concrete client', function () {
        $builder = new OperationInventoryBuilder(
            new RequestScanner(new ClassMapProvider()),
            new RequestSpecResolver(),
        );

        $inventory = $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs\\Inventory');

        expect($inventory->forRequest(InventoryStatusRequest::class)?->sdkCallPaths)->toBe([])
            ->and($inventory->forRequest(InventoryV1ReportRequest::class)?->sdkCallPaths)->toBe([]);
    });

    it('AbstractClient helper кеширует inventory на уровне экземпляра', function () {
        $client = new InventoryCallPathClient(
            new ClientConfig(baseUrl: 'https://api.test'),
            new MockTransport(),
        );

        $first = $client->operationInventory();
        $second = $client->operationInventory();

        expect($first)->toBe($second)
            ->and($first->forRequest(InventoryStatusRequest::class)?->sdkCallPaths)->toBe([
                'status()',
            ]);
    });

    it('AbstractClient helper кеширует responseDtoCatalog на уровне экземпляра', function () {
        $client = new InventoryCallPathClient(
            new ClientConfig(baseUrl: 'https://api.test'),
            new MockTransport(),
        );

        $first = $client->responseDtoCatalog();
        $second = $client->responseDtoCatalog();

        expect($first)->toBe($second)
            ->and($first->inventory())->toBe($client->operationInventory());
    });
});
