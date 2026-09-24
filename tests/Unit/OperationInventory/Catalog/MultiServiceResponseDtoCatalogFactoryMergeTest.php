<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Inventory\ResponseDtoKind;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\OperationInventory\Catalog\MultiServiceResponseDtoCatalogFactory;
use ApiSutra\OperationInventory\Catalog\ResponseDtoUsage;
use ApiSutra\OperationInventory\OperationInventoryBuilder;
use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\RequestScanner;
use ApiSutra\Tests\Stubs\CatalogMega\CatalogMegaClient;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceA\MegaServiceAClient;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Realty\Requests\GetRealtyObject\Dto\MegaRealtyObjectDto;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceB\MegaServiceBClient;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto\MegaTaxInfoFinalDto;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto\MegaTaxInfoStartDto;
use ApiSutra\Transport\MockTransport;

function apisutraMakeProviders(): array
{
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

    return ['standalone' => $standalone, 'mega' => $mega];
}

describe('MultiServiceResponseDtoCatalogFactory::merge', function () {
    it('объединяет одиночный клиент и мегаклиент в один каталог', function () {
        ['standalone' => $standalone, 'mega' => $mega] = apisutraMakeProviders();

        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge($standalone, $mega);

        $sync = $catalog->listSyncDtoClasses();
        $async = $catalog->listAsyncFinalDtoClasses();

        expect($sync)->toContain(MegaRealtyObjectDto::class)
            ->and($sync)->toContain(MegaTaxInfoStartDto::class)
            ->and($async)->toContain(MegaTaxInfoFinalDto::class);
    });

    it('serviceClass для одиночного клиента = FQCN самого клиента', function () {
        ['standalone' => $standalone, 'mega' => $mega] = apisutraMakeProviders();

        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge($standalone, $mega);
        $usage = $catalog->usagesFor(MegaRealtyObjectDto::class)[0];

        expect($usage->serviceClass)->toBe(MegaServiceAClient::class);
    });

    it('serviceClass для services() мегаклиента = FQCN сервиса', function () {
        ['standalone' => $standalone, 'mega' => $mega] = apisutraMakeProviders();

        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge($standalone, $mega);
        $usage = $catalog->usagesFor(MegaTaxInfoFinalDto::class)[0];

        expect($usage->serviceClass)->toBe(MegaServiceBClient::class);
    });

    it('при пустом списке провайдеров каталог пустой', function () {
        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge();

        expect($catalog->listAllDtoClasses())->toBe([])
            ->and($catalog->usages())->toBe([]);
    });

    it('первый провайдер с дублирующимся requestClass выигрывает', function () {
        $builder = new OperationInventoryBuilder(
            new RequestScanner(new ClassMapProvider()),
            new RequestSpecResolver(),
        );
        $config = new ClientConfig(baseUrl: 'https://api.test');
        $transport = new MockTransport();

        $serviceA1 = new MegaServiceAClient(
            $config,
            $transport,
            $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceA'),
        );
        $serviceA2 = new MegaServiceBClient(
            $config,
            $transport,
            $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs\\CatalogMega\\ServiceA'),
        );

        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge($serviceA1, $serviceA2);
        $usage = $catalog->usagesFor(MegaRealtyObjectDto::class)[0];

        expect($usage->serviceClass)->toBe(MegaServiceAClient::class);
    });

    it('каталог содержит usage из обоих провайдеров', function () {
        ['standalone' => $standalone, 'mega' => $mega] = apisutraMakeProviders();

        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge($standalone, $mega);
        $allUsages = [];
        foreach ($catalog->usages() as $items) {
            foreach ($items as $item) {
                $allUsages[] = $item;
            }
        }

        $servicesSeen = array_values(array_unique(array_map(
            static fn (ResponseDtoUsage $u) => $u->serviceClass,
            $allUsages,
        )));
        sort($servicesSeen);

        $expected = [MegaServiceAClient::class, MegaServiceBClient::class];
        sort($expected);

        expect($servicesSeen)->toBe($expected);
    });

    it('download usage из одиночного клиента сохраняет kind и serviceClass', function () {
        ['standalone' => $standalone, 'mega' => $mega] = apisutraMakeProviders();

        $catalog = (new MultiServiceResponseDtoCatalogFactory())->merge($standalone, $mega);
        $downloads = $catalog->listDownloadResponseClasses();

        expect($downloads)->not->toBeEmpty();

        $usages = $catalog->usagesFor($downloads[0]);
        expect($usages[0]->kind)->toBe(ResponseDtoKind::Download)
            ->and($usages[0]->serviceClass)->toBe(MegaServiceAClient::class);
    });

    it('бросает ConfigurationException на провайдер не-ClientInterface и не-MultiService', function () {
        $exoticProvider = new class implements \ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogProviderInterface {
            #[\Override]
            public function responseDtoCatalog(): \ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog
            {
                return new \ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog(
                    new \ApiSutra\OperationInventory\OperationInventory([]),
                );
            }
        };

        (new MultiServiceResponseDtoCatalogFactory())->merge($exoticProvider);
    })->throws(ConfigurationException::class);
});
