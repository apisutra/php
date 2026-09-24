<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

it('останавливает цикл A B A при отключённом maxPages и сообщает guard iterator', function (bool $iterator): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::sequence(array_map(
        static fn (string $cursor): MockResponse => MockResponse::success(['data' => [1], 'meta' => ['has_more' => true, 'next_cursor' => $cursor]]),
        ['fixture-A', 'fixture-B', 'fixture-A'],
    ))]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', paginationConfig: new PaginationConfig(maxPages: null)), $transport);
    $paginator = (new PaginatedRequest())->setClient($client)->paginate();
    if ($iterator) {
        $pages = iterator_to_array($paginator);
        expect($pages)->toHaveCount(4)->and($pages[3]->isFailed())->toBeTrue()->and($pages[3]->data)->toBeNull();
        $error = $pages[3]->errors->first();
    } else {
        $result = $paginator->all();
        expect($result->isPartial())->toBeTrue()->and($result->data)->toHaveCount(3);
        $error = $result->errors->first();
    }
    expect($error->code->value)->toBe('execution_error')
        ->and($error->context['reason'])->toBe('pagination_stalled')
        ->and(json_encode($error->context))->not->toContain('fixture-A')
        ->and($transport->getRecorded())->toHaveCount(3);
})->with([false, true]);

it('cursor Partial сохраняет ошибку страницы и не повторяет предыдущий cursor', function (int $status, bool $first): void {
    $transport = new MockTransport();
    $sequence = [MockResponse::make('original failure', $status, ['X-Page' => 'failed'])];
    if (!$first) {
        array_unshift($sequence, MockResponse::success(['data' => [0, false], 'meta' => ['has_more' => true, 'next_cursor' => '0']]));
    }
    $transport->fake(['*' => MockResponse::sequence($sequence)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', paginationConfig: new PaginationConfig(maxPages: 5)), $transport);
    $result = (new PaginatedRequest())->setClient($client)->paginate()->withFailStrategy(FailStrategy::Partial)->all();
    $error = $result->errors->first();
    expect($error->code->value)->toBe($status === 403 ? 'forbidden' : 'rate_limited')
        ->and($error->response->status)->toBe($status)
        ->and($error->response->body)->toBe('original failure')
        ->and($error->context['page'])->toBe($first ? 1 : 2)
        ->and($result->isPartial())->toBe(!$first)
        ->and($transport->getRecorded())->toHaveCount($first ? 1 : 2);
})->with([403, 429])->with([false, true]);

it('maxPages даёт один guard-result без дополнительного HTTP, pages остаётся нормальным ограничением', function (bool $explicit): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['data' => [1], 'meta' => ['has_more' => true, 'next_cursor' => 'next']])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', paginationConfig: new PaginationConfig(maxPages: 1)), $transport);
    $paginator = (new PaginatedRequest())->setClient($client)->paginate();
    if ($explicit) {
        expect($paginator->pages(1)->isSuccess())->toBeTrue();
    } else {
        $pages = iterator_to_array($paginator);
        expect($pages)->toHaveCount(2)
            ->and($pages[1]->errors->first()->context['reason'])->toBe('pagination_max_pages_reached');
    }
    expect($transport->getRecorded())->toHaveCount(1);
})->with([false, true]);
