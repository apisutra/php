<?php

declare(strict_types=1);

use ApiSutra\Enums\Inventory\ResponseDtoKind;
use ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;
use ApiSutra\OperationInventory\Catalog\ResponseDtoUsage;
use ApiSutra\OperationInventory\OperationInventoryBuilder;
use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\RequestScanner;
use ApiSutra\Tests\Stubs\Catalog\Resources\Files\Requests\DownloadAttachment\CatalogDownloadAttachmentRequest;
use ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\CreateByFio\CatalogCreateByFioRequest;
use ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\CreateByFio\Dto\CatalogCreateByFioFinalDto;
use ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\CreateByFio\Dto\CatalogCreateByFioStartDto;
use ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\PollTask\CatalogPollTaskRequest;
use ApiSutra\Tests\Stubs\Catalog\Resources\Users\Requests\GetUser\CatalogGetUserRequest;
use ApiSutra\Tests\Stubs\Catalog\Resources\Users\Requests\GetUser\Dto\CatalogUserDto;
use ApiSutra\Tests\Stubs\Catalog\Resources\Users\Requests\ListUsers\CatalogListUsersRequest;
use ApiSutra\Tests\Stubs\Catalog\Standalone\CatalogStandaloneRequest;
use ApiSutra\VO\Files\FileResponse;

function apisutraBuildCatalog(): ResponseDtoCatalog
{
    $builder = new OperationInventoryBuilder(
        new RequestScanner(new ClassMapProvider()),
        new RequestSpecResolver(),
    );

    $inventory = $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs\\Catalog');

    return new ResponseDtoCatalog($inventory);
}

describe('ResponseDtoCatalog', function () {
    it('собирает sync DTO из Returns', function () {
        $catalog = apisutraBuildCatalog();

        expect($catalog->listSyncDtoClasses())->toContain(CatalogUserDto::class)
            ->and($catalog->listSyncDtoClasses())->toContain(CatalogCreateByFioStartDto::class);
    });

    it('собирает async-final DTO из ContinuationResult', function () {
        $catalog = apisutraBuildCatalog();

        expect($catalog->listAsyncFinalDtoClasses())->toContain(CatalogCreateByFioFinalDto::class)
            ->and($catalog->listAsyncFinalDtoClasses())->not->toContain(CatalogCreateByFioStartDto::class);
    });

    it('listAllDtoClasses обьединяет sync + async без download', function () {
        $catalog = apisutraBuildCatalog();
        $all = $catalog->listAllDtoClasses();

        expect($all)->toContain(CatalogUserDto::class)
            ->and($all)->toContain(CatalogCreateByFioStartDto::class)
            ->and($all)->toContain(CatalogCreateByFioFinalDto::class)
            ->and($all)->not->toContain(FileResponse::class);
    });

    it('includeDownload=true добавляет FileResponse', function () {
        $catalog = apisutraBuildCatalog();
        $all = $catalog->listAllDtoClasses(includeDownload: true);

        expect($all)->toContain(FileResponse::class);
    });

    it('listDownloadResponseClasses возвращает только FileResponse', function () {
        $catalog = apisutraBuildCatalog();

        expect($catalog->listDownloadResponseClasses())->toBe([FileResponse::class]);
    });

    it('дедуплицирует DTO которые возвращаются разными request-ами', function () {
        $catalog = apisutraBuildCatalog();
        $sync = $catalog->listSyncDtoClasses();

        expect(count(array_keys($sync, CatalogUserDto::class, true)))->toBe(1);

        $usages = $catalog->usagesFor(CatalogUserDto::class);
        $requestClasses = array_map(static fn (ResponseDtoUsage $u) => $u->requestClass, $usages);

        expect($requestClasses)->toContain(CatalogGetUserRequest::class)
            ->and($requestClasses)->toContain(CatalogListUsersRequest::class);
    });

    it('async usage заполняет pollRequest и unwrap', function () {
        $catalog = apisutraBuildCatalog();
        $usages = $catalog->usagesFor(CatalogCreateByFioFinalDto::class);

        expect($usages)->toHaveCount(1);
        $usage = $usages[0];

        expect($usage->kind)->toBe(ResponseDtoKind::AsyncFinal)
            ->and($usage->requestClass)->toBe(CatalogCreateByFioRequest::class)
            ->and($usage->pollRequest)->toBe(CatalogPollTaskRequest::class)
            ->and($usage->unwrap)->toBe('result');
    });

    it('sync usage у того же async-стартового запроса заполняет returnsUnwrap', function () {
        $catalog = apisutraBuildCatalog();
        $usages = $catalog->usagesFor(CatalogCreateByFioStartDto::class);

        $startUsage = array_values(array_filter(
            $usages,
            static fn (ResponseDtoUsage $u): bool => $u->requestClass === CatalogCreateByFioRequest::class,
        ))[0];

        expect($startUsage->kind)->toBe(ResponseDtoKind::Sync)
            ->and($startUsage->unwrap)->toBe('data')
            ->and($startUsage->pollRequest)->toBeNull();
    });

    it('заполняет resourcePath/resourceLabel для usage', function () {
        $catalog = apisutraBuildCatalog();
        $usages = $catalog->usagesFor(CatalogCreateByFioFinalDto::class);
        $usage = $usages[0];

        expect($usage->resourcePath)->toBe(['Reports', 'Tasks'])
            ->and($usage->resourceLabel)->toBe('Reports / Tasks');
    });

    it('заполняет title/description/httpMethod/endpoint из view', function () {
        $catalog = apisutraBuildCatalog();
        $usage = $catalog->usagesFor(CatalogUserDto::class)[0];

        $allUserUsages = $catalog->usagesFor(CatalogUserDto::class);
        $getUsage = array_values(array_filter(
            $allUserUsages,
            static fn (ResponseDtoUsage $u) => $u->requestClass === CatalogGetUserRequest::class,
        ))[0];

        expect($getUsage->title)->toBe('Получить пользователя')
            ->and($getUsage->endpoint)->toBe('/catalog/users')
            ->and($getUsage->httpMethod?->value)->toBe('GET');
    });

    it('download usage помечается ResponseDtoKind::Download и FileResponse', function () {
        $catalog = apisutraBuildCatalog();
        $usages = $catalog->usagesFor(FileResponse::class);

        expect($usages)->toHaveCount(1);
        $usage = $usages[0];

        expect($usage->kind)->toBe(ResponseDtoKind::Download)
            ->and($usage->requestClass)->toBe(CatalogDownloadAttachmentRequest::class)
            ->and($usage->responseClass)->toBe(FileResponse::class)
            ->and($usage->resourceLabel)->toBe('Files');
    });

    it('игнорирует request без response/continuation/download', function () {
        $catalog = apisutraBuildCatalog();
        $allWithDownload = $catalog->listAllDtoClasses(includeDownload: true);

        $standalonePresent = array_any(
            $catalog->usages(),
            static function (array $items) {
                return array_any(
                    $items,
                    static fn (ResponseDtoUsage $u): bool => $u->requestClass === CatalogStandaloneRequest::class,
                );
            },
        );

        expect($standalonePresent)->toBeFalse()
            ->and($allWithDownload)->not->toBeEmpty();
    });
});
