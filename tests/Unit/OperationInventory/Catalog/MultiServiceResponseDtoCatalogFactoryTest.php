<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Inventory\ResponseDtoKind;
use ApiSutra\OperationInventory\Catalog\MultiServiceResponseDtoCatalogFactory;
use ApiSutra\OperationInventory\Catalog\ResponseDtoUsage;
use ApiSutra\OperationInventory\OperationInventoryBuilder;
use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\RequestScanner;
use ApiSutra\Tests\Stubs\CatalogMega\CatalogMegaClient;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceA\MegaServiceAClient;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Files\Requests\DownloadFile\MegaServiceADownloadFileRequest;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Realty\Requests\GetRealtyObject\Dto\MegaRealtyObjectDto;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Realty\Requests\GetRealtyObject\MegaGetRealtyObjectRequest;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceB\MegaServiceBClient;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto\MegaTaxInfoFinalDto;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto\MegaTaxInfoStartDto;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\MegaGetTaxInfoRequest;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\PollTax\MegaPollTaxRequest;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Files\FileResponse;

function apisutraMakeMegaClient(): CatalogMegaClient
{
    $builder = new OperationInventoryBuilder(
        new RequestScanner(new ClassMapProvider()),
        new RequestSpecResolver(),
    );

    $serviceAInventory = $builder->buildForRootNamespace(
        'ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceA',
    );
    $serviceBInventory = $builder->buildForRootNamespace(
        'ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceB',
    );

    $config = new ClientConfig(baseUrl: 'https://api.test');
    $transport = new MockTransport();

    $serviceA = new MegaServiceAClient($config, $transport, $serviceAInventory);
    $serviceB = new MegaServiceBClient($config, $transport, $serviceBInventory);

    return new CatalogMegaClient([$serviceA, $serviceB]);
}

describe('MultiServiceResponseDtoCatalogFactory', function () {
    it('собирает каталог по всему мегаклиенту через services()', function () {
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService(apisutraMakeMegaClient());

        $sync = $catalog->listSyncDtoClasses();
        $async = $catalog->listAsyncFinalDtoClasses();

        expect($sync)->toContain(MegaRealtyObjectDto::class)
            ->and($sync)->toContain(MegaTaxInfoStartDto::class)
            ->and($async)->toContain(MegaTaxInfoFinalDto::class);
    });

    it('проставляет serviceClass на usage для каждого сервиса', function () {
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService(apisutraMakeMegaClient());

        $realtyUsage = $catalog->usagesFor(MegaRealtyObjectDto::class)[0];
        $taxFinalUsage = $catalog->usagesFor(MegaTaxInfoFinalDto::class)[0];

        expect($realtyUsage->serviceClass)->toBe(MegaServiceAClient::class)
            ->and($taxFinalUsage->serviceClass)->toBe(MegaServiceBClient::class)
            ->and($taxFinalUsage->pollRequest)->toBe(MegaPollTaxRequest::class);
    });

    it('download usage помечается serviceClass и FileResponse', function () {
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService(apisutraMakeMegaClient());

        $usages = $catalog->usagesFor(FileResponse::class);
        expect($usages)->toHaveCount(1);

        $usage = $usages[0];
        expect($usage->kind)->toBe(ResponseDtoKind::Download)
            ->and($usage->requestClass)->toBe(MegaServiceADownloadFileRequest::class)
            ->and($usage->serviceClass)->toBe(MegaServiceAClient::class);
    });

    it('каталог объединяет сервисы в один listAllDtoClasses', function () {
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService(apisutraMakeMegaClient());

        $all = $catalog->listAllDtoClasses();

        expect($all)->toContain(MegaRealtyObjectDto::class)
            ->and($all)->toContain(MegaTaxInfoStartDto::class)
            ->and($all)->toContain(MegaTaxInfoFinalDto::class)
            ->and($all)->not->toContain(FileResponse::class);
    });

    it('пустой services() даёт пустой каталог', function () {
        $mega = new CatalogMegaClient([]);
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService($mega);

        expect($catalog->listAllDtoClasses())->toBe([])
            ->and($catalog->usages())->toBe([]);
    });

    it('start request у async имеет sync usage с serviceClass из своего сервиса', function () {
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService(apisutraMakeMegaClient());

        $startUsages = $catalog->usagesFor(MegaTaxInfoStartDto::class);
        expect($startUsages)->toHaveCount(1);

        $startUsage = $startUsages[0];

        expect($startUsage->kind)->toBe(ResponseDtoKind::Sync)
            ->and($startUsage->requestClass)->toBe(MegaGetTaxInfoRequest::class)
            ->and($startUsage->serviceClass)->toBe(MegaServiceBClient::class);
    });

    it('trait responseDtoCatalog кеширует каталог на инстансе', function () {
        $mega = apisutraMakeMegaClient();

        $first = $mega->responseDtoCatalog();
        $second = $mega->responseDtoCatalog();

        expect($first)->toBe($second);
    });
});
