<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Inventory\ServiceLabelResolverInterface;
use ApiSutra\OperationInventory\Catalog\Export\MarkdownResponseDtoCatalogExporter;
use ApiSutra\OperationInventory\Catalog\MultiServiceResponseDtoCatalogFactory;
use ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;
use ApiSutra\OperationInventory\OperationInventoryBuilder;
use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\RequestScanner;
use ApiSutra\Tests\Stubs\CatalogMega\CatalogMegaClient;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceA\MegaServiceAClient;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceB\MegaServiceBClient;
use ApiSutra\Transport\MockTransport;

function apisutraMakeMultiServiceCatalog(): ResponseDtoCatalog
{
    $builder = new OperationInventoryBuilder(
        new RequestScanner(new ClassMapProvider()),
        new RequestSpecResolver(),
    );

    $serviceA = new MegaServiceAClient(
        new ClientConfig(baseUrl: 'https://api.test'),
        new MockTransport(),
        $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceA'),
    );
    $serviceB = new MegaServiceBClient(
        new ClientConfig(baseUrl: 'https://api.test'),
        new MockTransport(),
        $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceB'),
    );

    $mega = new CatalogMegaClient([$serviceA, $serviceB]);

    return (new MultiServiceResponseDtoCatalogFactory())->fromMultiService($mega);
}

function apisutraMakeSingleServiceCatalog(): ResponseDtoCatalog
{
    $builder = new OperationInventoryBuilder(
        new RequestScanner(new ClassMapProvider()),
        new RequestSpecResolver(),
    );
    $inventory = $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs\\Catalog');

    return new ResponseDtoCatalog($inventory);
}

describe('MarkdownResponseDtoCatalogExporter в multi-service режиме', function () {
    it('добавляет секцию "## Сервисы" со счётчиками по сервисам', function () {
        $exporter = new MarkdownResponseDtoCatalogExporter();
        $markdown = $exporter->export(apisutraMakeMultiServiceCatalog());

        expect($markdown)->toContain('## Services')
            ->and($markdown)->toContain('| Service | Sync | AsyncFinal | Download |')
            ->and($markdown)->toContain('MegaServiceAClient')
            ->and($markdown)->toContain('MegaServiceBClient');
    });

    it('добавляет колонку Service в by-resource секции', function () {
        $exporter = new MarkdownResponseDtoCatalogExporter();
        $markdown = $exporter->export(apisutraMakeMultiServiceCatalog());

        expect($markdown)->toContain('| Service | Request | HTTP | Endpoint | Kind | Response | Title |');
    });

    it('добавляет колонку Service в by-DTO секции', function () {
        $exporter = new MarkdownResponseDtoCatalogExporter();
        $markdown = $exporter->export(apisutraMakeMultiServiceCatalog());

        expect($markdown)->toContain('| Service | Request | Kind | Poll request | Unwrap |');
    });

    it('добавляет колонку Service в Download секции', function () {
        $exporter = new MarkdownResponseDtoCatalogExporter();
        $markdown = $exporter->export(apisutraMakeMultiServiceCatalog());

        expect($markdown)->toContain('| Service | Request | HTTP | Endpoint | Response | Resource |');
    });

    it('применяет custom ServiceLabelResolver', function () {
        $resolver = new class implements ServiceLabelResolverInterface {
            #[\Override]
            public function resolve(string $serviceClass): string
            {
                return str_replace(['MegaService', 'Client'], '', $this->shortName($serviceClass));
            }

            private function shortName(string $fqn): string
            {
                $position = strrpos($fqn, '\\');

                return $position === false ? $fqn : substr($fqn, $position + 1);
            }
        };

        $exporter = new MarkdownResponseDtoCatalogExporter($resolver);
        $markdown = $exporter->export(apisutraMakeMultiServiceCatalog());

        expect($markdown)->toContain('| A |')
            ->and($markdown)->toContain('| B |');
    });

    it('single-service catalog НЕ содержит секцию "## Сервисы" и НЕ имеет колонки Service', function () {
        $exporter = new MarkdownResponseDtoCatalogExporter();
        $markdown = $exporter->export(apisutraMakeSingleServiceCatalog());

        expect($markdown)->not->toContain('## Services')
            ->and($markdown)->not->toContain('| Service | Request')
            ->and($markdown)->toContain('| Request | HTTP | Endpoint | Kind | Response | Title |');
    });
});
