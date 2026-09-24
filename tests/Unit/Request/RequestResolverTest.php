<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Result\PaginatedResult;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('RequestResolver и PaginationRule', function () {
    it('по умолчанию выполняет один запрос', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'data' => [1],
                    'meta' => [
                        'page' => 1,
                        'per_page' => 1,
                        'total' => 2,
                        'has_more' => true,
                    ],
                ]),
                MockResponse::success([
                    'data' => [2],
                    'meta' => [
                        'page' => 2,
                        'per_page' => 1,
                        'total' => 2,
                        'has_more' => false,
                    ],
                ]),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new PaginatedRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result)->not->toBeInstanceOf(PaginatedResult::class)
            ->and($transport->getRecorded())->toHaveCount(1);
    });

    it('использует PaginationRule::all из конфига', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'data' => [1],
                    'meta' => [
                        'page' => 1,
                        'per_page' => 1,
                        'total' => 2,
                        'has_more' => true,
                        'next_cursor' => 'c-2',
                    ],
                ]),
                MockResponse::success([
                    'data' => [2],
                    'meta' => [
                        'page' => 2,
                        'per_page' => 1,
                        'total' => 2,
                        'has_more' => false,
                    ],
                ]),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                paginationRule: PaginationRule::all(),
            ),
            $transport,
        );

        $request = new PaginatedRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result)->toBeInstanceOf(PaginatedResult::class)
            ->and($result->data)->toBe([1, 2])
            ->and($transport->getRecorded())->toHaveCount(2);

        $recorded = $transport->getRecorded();
        $firstQuery = $recorded[0]->meta['query'] ?? [];
        $secondQuery = $recorded[1]->meta['query'] ?? [];
        expect($firstQuery['cursor']['value'] ?? null)->toBeNull()
            ->and($secondQuery['cursor']['value'] ?? null)->toBe('c-2')
            ->and($secondQuery['page']['value'] ?? null)->toBe(2);
    });

    it('rules переопределяет правила пагинации', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'data' => [1],
                    'meta' => [
                        'page' => 1,
                        'per_page' => 1,
                        'total' => 2,
                        'has_more' => true,
                        'next_cursor' => 'c-2',
                    ],
                ]),
                MockResponse::success([
                    'data' => [2],
                    'meta' => [
                        'page' => 2,
                        'per_page' => 1,
                        'total' => 2,
                        'has_more' => false,
                    ],
                ]),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new PaginatedRequest();
        $request->setClient($client);

        $result = $request->rules(PaginationRule::pages(2))->send()->raw();

        expect($result)->toBeInstanceOf(PaginatedResult::class)
            ->and($result->data)->toBe([1, 2])
            ->and($transport->getRecorded())->toHaveCount(2);

        $recorded = $transport->getRecorded();
        $firstQuery = $recorded[0]->meta['query'] ?? [];
        $secondQuery = $recorded[1]->meta['query'] ?? [];
        expect($firstQuery['cursor']['value'] ?? null)->toBeNull()
            ->and($secondQuery['cursor']['value'] ?? null)->toBe('c-2')
            ->and($secondQuery['page']['value'] ?? null)->toBe(2);
    });

    it('PaginationRule::range задаёт диапазон страниц', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'data' => [1],
                    'meta' => [
                        'page' => 2,
                        'per_page' => 1,
                        'total' => 3,
                        'has_more' => true,
                        'next_cursor' => 'c-3',
                    ],
                ]),
                MockResponse::success([
                    'data' => [2],
                    'meta' => [
                        'page' => 3,
                        'per_page' => 1,
                        'total' => 3,
                        'has_more' => false,
                    ],
                ]),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                paginationRule: PaginationRule::range(2, 3),
            ),
            $transport,
        );

        $request = new PaginatedRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result)->toBeInstanceOf(PaginatedResult::class);
        $recorded = $transport->getRecorded();
        $firstPage = $recorded[0]->meta['query']['page']['value'] ?? null;
        $secondPage = $recorded[1]->meta['query']['page']['value'] ?? null;
        $firstCursor = $recorded[0]->meta['query']['cursor']['value'] ?? null;
        $secondCursor = $recorded[1]->meta['query']['cursor']['value'] ?? null;
        expect($firstPage)->toBe(2)
            ->and($secondPage)->toBe(3)
            ->and($firstCursor)->toBeNull()
            ->and($secondCursor)->toBe('c-3');
    });
});
