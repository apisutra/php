<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Pagination\PageRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

it('копирует настройки и не переносит начало range на повторный all', function (): void {
    $transport = new MockTransport();
    $limits = [];
    $transport->fake(['*' => function (PageRequest $request) use (&$limits): MockResponse {
        $options = $request->getContext()->paginationOptions;
        $page = $options->getPage();
        $limits[] = $options->getLimit();
        return MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'has_more' => $page < 3]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test'), $transport);
    $base = new PageRequest()->setClient($client)->paginate();
    $copy = $base->withPerPage(5)->withConcurrency(2)->withFailStrategy(FailStrategy::Partial);
    expect($copy)->not->toBe($base)->and($copy->range(2, 3)->items())->toBe([2, 3]);
    expect($base->all()->items())->toBe([1, 2, 3])->and($limits)->toBe([5, 5, null, null, null]);
    expect(new PageRequest()->setClient($client)->withPage(2)->paginate()->pages(2)->items())->toBe([2, 3]);
});

it('наследует целое правило runtime или клиента, iterator требует явной единицы', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['data' => [1]])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test', paginationRule: PaginationRule::all(concurrency: 5)), $transport);
    $request = new PageRequest()->setClient($client);
    $pages = iterator_to_array($request->paginate());
    expect($pages[0]->errors->first()->code->value)->toBe('configuration_error')->and($transport->getRecorded())->toBe([]);
    expect(iterator_to_array($request->rules(PaginationRule::all())->paginate())[0]->isSuccess())->toBeTrue();
    expect(iterator_to_array($request->paginate()->withConcurrency(1))[0]->isSuccess())->toBeTrue();
});

it('проверяет аргументы builder и фабрик правила одинаково', function (): void {
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test'), new MockTransport());
    $paginator = new PageRequest()->setClient($client)->paginate();
    foreach ([fn () => $paginator->withPerPage(0), fn () => $paginator->withConcurrency(0), fn () => PaginationRule::all(concurrency: 0),
        fn () => $paginator->pages(0), fn () => PaginationRule::pages(0), fn () => $paginator->range(2, 1), fn () => PaginationRule::range(2, 1)] as $invalid) {
        expect($invalid)->toThrow(ConfigurationException::class);
    }
});
