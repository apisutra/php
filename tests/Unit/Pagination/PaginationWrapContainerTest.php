<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Dto\PaginationContainerDto;
use ApiSutra\Tests\Stubs\Dto\PaginationItemDto;
use ApiSutra\Tests\Stubs\Pagination\TestItemCollection;
use ApiSutra\Tests\Stubs\Pagination\TestItemCollectionFactory;
use ApiSutra\Tests\Stubs\Requests\FactoryWrappedPaginatedRequest;
use ApiSutra\Tests\Stubs\Requests\WrappedPaginatedRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('Pagination wrap container', function () {
    it('возвращает DTO-контейнер с типизированными items', function () {
        $transport = new MockTransport();
        $transport->fake([
            WrappedPaginatedRequest::class => MockResponse::success([
                'response' => [
                    'count' => 2,
                    'result' => [
                        ['id' => 'a'],
                        ['id' => 'b'],
                    ],
                ],
            ]),
        ]);

        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $client = new TestClient($config, $transport);
        $request = new WrappedPaginatedRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->data)->toBeInstanceOf(PaginationContainerDto::class);

        $items = $result->data->items();
        expect($items)->toBeInstanceOf(TestItemCollection::class);
        $itemsArray = $items->toArray();
        expect($itemsArray[0])->toBeInstanceOf(PaginationItemDto::class);
    });

    it('агрегирует items через пагинатор', function () {
        $transport = new MockTransport();
        $transport->fake([
            WrappedPaginatedRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'meta' => [
                        'has_more' => true,
                    ],
                    'response' => [
                        'count' => 2,
                        'result' => [
                            ['id' => 'a'],
                        ],
                    ],
                ]),
                MockResponse::success([
                    'meta' => [
                        'has_more' => false,
                    ],
                    'response' => [
                        'count' => 2,
                        'result' => [
                            ['id' => 'b'],
                        ],
                    ],
                ]),
            ]),
        ]);

        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $client = new TestClient($config, $transport);
        $request = new WrappedPaginatedRequest();
        $request->setClient($client);

        $result = $request->paginate()->withPerPage(1)->all();

        $items = $result->items();
        expect($items)->toBeInstanceOf(TestItemCollection::class);
        expect($items->count())->toBe(2);
    });

    it('использует фабрику коллекции', function () {
        TestItemCollectionFactory::reset();

        $transport = new MockTransport();
        $transport->fake([
            FactoryWrappedPaginatedRequest::class => MockResponse::success([
                'response' => [
                    'count' => 1,
                    'result' => [
                        ['id' => 'a'],
                    ],
                ],
            ]),
        ]);

        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $client = new TestClient($config, $transport);
        $request = new FactoryWrappedPaginatedRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect(TestItemCollectionFactory::$called)->toBeTrue();
        expect($result->data->items())->toBeInstanceOf(TestItemCollection::class);
    });
});
