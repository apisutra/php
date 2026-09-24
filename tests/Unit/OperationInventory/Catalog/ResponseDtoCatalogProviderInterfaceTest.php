<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogProviderInterface;
use ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;
use ApiSutra\OperationInventory\OperationInventoryBuilder;
use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\RequestScanner;
use ApiSutra\Tests\Stubs\CatalogMega\CatalogMegaClient;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceA\MegaServiceAClient;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceB\MegaServiceBClient;
use ApiSutra\Tests\Stubs\Inventory\InventoryCallPathClient;
use ApiSutra\Transport\MockTransport;

describe('ResponseDtoCatalogProviderInterface', function () {
    it('AbstractClient имплементирует контракт', function () {
        $client = new InventoryCallPathClient(
            new ClientConfig(baseUrl: 'https://api.test'),
            new MockTransport(),
        );

        expect($client)->toBeInstanceOf(ResponseDtoCatalogProviderInterface::class)
            ->and($client->responseDtoCatalog())->toBeInstanceOf(ResponseDtoCatalog::class);
    });

    it('мегаклиент через trait тоже имплементирует контракт', function () {
        $mega = new CatalogMegaClient([]);

        expect($mega)->toBeInstanceOf(ResponseDtoCatalogProviderInterface::class)
            ->and($mega->responseDtoCatalog())->toBeInstanceOf(ResponseDtoCatalog::class);
    });

    it('унифицированный полиморфный цикл по разным провайдерам', function () {
        $builder = new OperationInventoryBuilder(
            new RequestScanner(new ClassMapProvider()),
            new RequestSpecResolver(),
        );
        $config = new ClientConfig(baseUrl: 'https://api.test');
        $transport = new MockTransport();

        $standalone = new MegaServiceAClient(
            $config,
            $transport,
            $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceA'),
        );
        $mega = new CatalogMegaClient([
            new MegaServiceBClient(
                $config,
                $transport,
                $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceB'),
            ),
        ]);

        /** @var array<int, ResponseDtoCatalogProviderInterface> $providers */
        $providers = [$standalone, $mega];

        $catalogs = [];
        foreach ($providers as $provider) {
            $catalogs[] = $provider->responseDtoCatalog();
        }

        expect($catalogs)->toHaveCount(2)
            ->and($catalogs[0])->toBeInstanceOf(ResponseDtoCatalog::class)
            ->and($catalogs[1])->toBeInstanceOf(ResponseDtoCatalog::class);
    });
});
