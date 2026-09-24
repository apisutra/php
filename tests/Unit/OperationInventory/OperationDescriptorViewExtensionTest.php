<?php

declare(strict_types=1);

use ApiSutra\OperationInventory\OperationInventoryBuilder;
use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\RequestScanner;
use ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\CreateByFio\CatalogCreateByFioRequest;
use ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\CreateByFio\Dto\CatalogCreateByFioFinalDto;
use ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\PollTask\CatalogPollTaskRequest;
use ApiSutra\Tests\Stubs\Catalog\Resources\Users\Requests\GetUser\CatalogGetUserRequest;

describe('OperationDescriptorView async/resource fields', function () {
    it('пробрасывает ContinuationResult fields и returnsUnwrap', function () {
        $builder = new OperationInventoryBuilder(
            new RequestScanner(new ClassMapProvider()),
            new RequestSpecResolver(),
        );
        $inventory = $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs\\Catalog');

        $view = $inventory->forRequest(CatalogCreateByFioRequest::class);

        expect($view)->not->toBeNull()
            ->and($view->isAsync())->toBeTrue()
            ->and($view->continuationFinalType)->toBe(CatalogCreateByFioFinalDto::class)
            ->and($view->continuationUnwrap)->toBe('result')
            ->and($view->pollRequestClass)->toBe(CatalogPollTaskRequest::class)
            ->and($view->returnsUnwrap)->toBe('data');
    });

    it('заполняет resourcePath и resourceLabel по дефолтной эвристике', function () {
        $builder = new OperationInventoryBuilder(
            new RequestScanner(new ClassMapProvider()),
            new RequestSpecResolver(),
        );
        $inventory = $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs\\Catalog');

        $async = $inventory->forRequest(CatalogCreateByFioRequest::class);
        $get = $inventory->forRequest(CatalogGetUserRequest::class);

        expect($async?->resourcePath)->toBe(['Reports', 'Tasks'])
            ->and($async?->resourceLabel)->toBe('Reports / Tasks')
            ->and($get?->resourcePath)->toBe(['Users'])
            ->and($get?->resourceLabel)->toBe('Users');
    });

    it('isAsync=false для sync-only request', function () {
        $builder = new OperationInventoryBuilder(
            new RequestScanner(new ClassMapProvider()),
            new RequestSpecResolver(),
        );
        $inventory = $builder->buildForRootNamespace('ApiSutra\\Tests\\Stubs\\Catalog');

        $view = $inventory->forRequest(CatalogGetUserRequest::class);

        expect($view?->isAsync())->toBeFalse()
            ->and($view?->continuationFinalType)->toBeNull();
    });
});
