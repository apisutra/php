<?php

declare(strict_types=1);

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\PaginatedResult;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Execution\DeferredExecutor;
use ApiSutra\Tests\Stubs\Execution\ExecutorClient;
use ApiSutra\Tests\Stubs\Pagination\PageRequest;
use ApiSutra\Tests\Stubs\Requests\OffsetPaginatedRequest;
use ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use ApiSutra\Tests\Stubs\ResultContract\ProviderFailure;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Errors\RequestError;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Metadata\PaginationMeta;
use Revolt\EventLoop;

it('собирает страницы и meta по номеру, пополняя окно по готовности', function (bool $async): void {
    $transport = new MockTransport();
    $active = $peak = 0;
    $started = $finished = [];
    $transport->fake(['*' => function (PageRequest $request) use (&$active, &$peak, &$started, &$finished): MockResponse {
        $context = $request->getContext();
        $page = $context->paginationOptions->getPage();
        $started[] = $page;
        $peak = max($peak, ++$active);
        AsyncTask::current()->runtime->sleep($page === 2 ? 40 : 2);
        expect($request->getContext())->toBe($context);
        $finished[] = $page;
        --$active;
        return MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => 6]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test'), $transport);
    $request = new PageRequest()->setClient($client);
    $result = $async ? $request->rules(PaginationRule::all(concurrency: 3))->sendAsync()->wait()->raw()
        : $request->paginate()->withConcurrency(3)->all();
    expect($result->items())->toBe([1, 2, 3, 4, 5, 6])->and($result->meta()->currentPage)->toBe(6)
        ->and($started)->toBe([1, 2, 3, 4, 5, 6])->and($finished[0])->toBe(1)->and(end($finished))->toBe(2)
        ->and($peak)->toBe(3)->and($active)->toBe(0)->and($result->nested)->toHaveCount(6);
    foreach ($result->nested as $index => $page) {
        expect($page->data)->toBe([$index + 1])->and($page->trace->parentExecutionId)->toBe($result->trace->executionId);
    }
    expect($request->getContext()->nested)->toBe($result->nested);
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
})->with([false, true]);

it('использует запрошенную страницу при отсутствии номера в meta', function (bool $offset, int $concurrency): void {
    $transport = new MockTransport();
    $transport->fake(['*' => function ($request) use ($offset): MockResponse {
        $position = $request->getContext()->paginationOptions->getPage();
        $page = $offset ? intdiv($position, 2) + 1 : $position;
        return MockResponse::success(['data' => [$page], 'meta' => ['per_page' => 2, 'total' => 8]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test'), $transport);
    $request = ($offset ? new OffsetPaginatedRequest() : new PageRequest())->setClient($client);
    $result = $request->withPage(2)->paginate()->withPerPage(2)->withConcurrency($concurrency)->all();
    expect($result->isSuccess())->toBeTrue()->and($result->items())->toBe([2, 3, 4])
        ->and($result->meta()->currentPage)->toBe(4)->and($result->meta()->hasMore)->toBeFalse()
        ->and($transport->getRecorded())->toHaveCount(3);
})->with([false, true])->with([1, 3]);

it('не подменяет явно возвращённый провайдером номер страницы', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['data' => [1], 'meta' => ['page' => 1, 'per_page' => 2, 'total' => 8]])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test'), $transport);
    $result = new PageRequest()->setClient($client)->paginate()->withConcurrency(3)->range(2, 4);
    expect($result->errors->first()->context['reason'])->toBe('pagination_metadata_changed')
        ->and($transport->getRecorded())->toHaveCount(1);
});

it('читает meta ответа пользовательского executor без включения debug', function (): void {
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://pagination.test'), new MockTransport());
    $executor = new DeferredExecutor($client->execution());
    $client->executorOverride = $executor;
    foreach ([1, 2] as $page) {
        $executor->overrides[] = new ExecutionResult(
            data: [$page], status: ResultStatus::SUCCESS, errors: new ErrorCollection([]),
            response: new ProviderResponse(200, [], json_encode(['meta' => ['page' => $page, 'per_page' => 1, 'total' => 2]]), new PreparedRequest(HttpMethod::GET, 'https://pagination.test/pages'), 0),
        );
    }
    $result = new PageRequest()->setClient($client)->paginate()->withConcurrency(2)->all();
    expect($result->isSuccess())->toBeTrue()->and($result->items())->toBe([1, 2])->and($executor->issued)->toBe(2);
});

it('различает конечную выборку, неизвестный all и естественный конец bootstrap', function (string $mode, bool $hasMore): void {
    $transport = new MockTransport();
    $transport->fake(['*' => function (PageRequest $request) use ($hasMore): MockResponse {
        $page = $request->getContext()->paginationOptions->getPage();
        return MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 1, 'has_more' => $hasMore]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test'), $transport);
    $paginator = new PageRequest()->setClient($client)->withPage(3)->paginate()->withConcurrency(3);
    $result = match ($mode) {'all' => $paginator->all(), 'pages' => $paginator->pages(2), 'range' => $paginator->range(3, 4)};
    if ($mode === 'all' && $hasMore) {
        expect($result->isFailed())->toBeTrue()->and($result->errors->first()->code->value)->toBe('configuration_error')
            ->and($result->nested)->toHaveCount(1)->and($result->data)->toBeNull();
    } else {
        expect($result->isSuccess())->toBeTrue()->and($result->items())->toBe($hasMore ? [3, 4] : [3]);
    }
    expect($transport->getRecorded())->toHaveCount($hasMore && $mode !== 'all' ? 2 : 1);
})->with(['all', 'pages', 'range'])->with([false, true]);

it('отклоняет cursor до HTTP либо сразу после обнаружения в bootstrap', function (bool $known): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['data' => [1], 'meta' => ['has_more' => true, 'next_cursor' => 'secret']])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test'), $transport);
    $request = $known ? new PaginatedRequest() : new PageRequest();
    $result = $request->setClient($client)->paginate()->withConcurrency(2)->all();
    expect($result->errors->first()->code->value)->toBe('configuration_error')->and($result->isFailed())->toBeTrue()
        ->and($transport->getRecorded())->toHaveCount($known ? 0 : 1);
})->with([false, true]);

it('считает maxPages по выданным запросам и не добавляет guard на естественном конце', function (int $total): void {
    $transport = new MockTransport();
    $transport->fake(['*' => function (PageRequest $request) use ($total): MockResponse {
        $page = $request->getContext()->paginationOptions->getPage();
        AsyncTask::current()->runtime->sleep(1);
        return MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => $total]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test', paginationConfig: new PaginationConfig(maxPages: 3)), $transport);
    $result = new PageRequest()->setClient($client)->paginate()->withConcurrency(100)->all();
    expect($result->items())->toBe([1, 2, 3])->and($transport->getRecorded())->toHaveCount(3)
        ->and($result->isPartial())->toBe($total > 3);
    if ($total > 3) {
        expect($result->errors->first()->context['reason'])->toBe('pagination_max_pages_reached');
    }
})->with([3, 1000000]);

it('останавливает выдачу при раннем конце или guard и сохраняет уже начатые ответы', function (string $change): void {
    $transport = new MockTransport();
    $transport->fake(['*' => function (PageRequest $request) use ($change): MockResponse {
        $page = $request->getContext()->paginationOptions->getPage();
        AsyncTask::current()->runtime->sleep($page === 2 ? 20 : 1);
        $meta = ['page' => $page, 'per_page' => 1, 'total' => 8];
        if ($page === 3) {
            $meta = match ($change) {
                'end' => $meta + ['has_more' => false],
                'shrink' => array_replace($meta, ['total' => 3]),
                'size' => array_replace($meta, ['per_page' => 2]),
                'page' => array_replace($meta, ['page' => 2]),
            };
        }
        return MockResponse::success(['data' => [$page], 'meta' => $meta]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test'), $transport);
    $result = new PageRequest()->setClient($client)->paginate()->withConcurrency(2)->all();
    expect($result->items())->toBe([1, 2, 3])->and($transport->getRecorded())->toHaveCount(3);
    expect($result->isPartial())->toBe(in_array($change, ['size', 'page'], true));
    if ($result->isPartial()) {
        expect($result->errors->first()->context)->toMatchArray(['page' => 3, 'reason' => 'pagination_metadata_changed']);
    }
})->with(['end', 'shrink', 'size', 'page']);

it('замораживает верхнюю границу all и передаёт эффективный размер остальным страницам', function (): void {
    $transport = new MockTransport();
    $limits = [];
    $transport->fake(['*' => function (PageRequest $request) use (&$limits): MockResponse {
        $pagination = $request->getContext()->paginationOptions;
        $page = $pagination->getPage();
        $limits[] = $pagination->getLimit();
        return MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 2, 'total' => $page === 1 ? 6 : 100]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test'), $transport);
    $result = new PageRequest()->setClient($client)->paginate()->withConcurrency(2)->all();
    expect($result->items())->toBe([1, 2, 3])->and($limits)->toBe([null, 2, 2]);
});

it('FailAll завершает начатое, остальные стратегии сохраняют ошибки и продолжают', function (FailStrategy $strategy): void {
    $transport = new MockTransport();
    $transport->fake(['*' => function (PageRequest $request): MockResponse {
        $page = $request->getContext()->paginationOptions->getPage();
        AsyncTask::current()->runtime->sleep($page === 2 ? 20 : 1);
        return in_array($page, [2, 3], true) ? MockResponse::make('failure', $page === 2 ? 403 : 404)
            : MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => 5]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test'), $transport);
    $result = new PageRequest()->setClient($client)->paginate()->withConcurrency(2)->withFailStrategy($strategy)->all();
    expect($result->isPartial())->toBeTrue()->and($result->items())->toBe($strategy === FailStrategy::FailAll ? [1] : [1, 4, 5]);
    expect(array_map(fn ($error) => $error->context['page'], $result->errors->all()))->toBe([2, 3]);
})->with(FailStrategy::cases());

it('offset range начинает с from, не запрашивает первую страницу и проверяет переполнение', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => function (OffsetPaginatedRequest $request): MockResponse {
        $offset = $request->getContext()->paginationOptions->getPage();
        return MockResponse::success(['data' => [$offset], 'meta' => ['page' => intdiv($offset, 10) + 1, 'per_page' => 10, 'has_more' => true]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test'), $transport);
    $paginator = new OffsetPaginatedRequest()->setClient($client)->paginate()->withConcurrency(3);
    expect($paginator->withPerPage(10)->range(3, 5)->items())->toBe([20, 30, 40]);
    expect($paginator->range(3, 5)->errors->first()->code->value)->toBe('configuration_error');
    expect($paginator->withPerPage(PHP_INT_MAX)->range(2, 3)->errors->first()->code->value)->toBe('configuration_error');
    expect($transport->getRecorded())->toHaveCount(3);
});

it('не скрывает коллизию строковых ключей при конкурентной сборке', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => function (PageRequest $request): MockResponse {
        $page = $request->getContext()->paginationOptions->getPage();
        return MockResponse::success(['data' => ['shared' => $page, 12 => $page], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => 2]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test'), $transport);
    $result = new PageRequest()->setClient($client)->paginate()->withConcurrency(2)->all();
    expect($result->isFailed())->toBeTrue()->and($result->data)->toBeNull()
        ->and($result->errors->first()->context['reason'])->toBe('pagination_item_key_collision')
        ->and($result->pages()->all())->toHaveCount(2);
});

it('выбирает первый failed по номеру, даже если его exception равен null', function (): void {
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://pagination.test'), new MockTransport());
    $executor = new DeferredExecutor($client->execution(), reverse: true);
    $executor->overrides = [
        new ExecutionResult([1], ResultStatus::SUCCESS, new ErrorCollection([]), meta: new PaginationMeta(3, 1, 1, true)),
        new ExecutionResult(null, ResultStatus::FAILED, new ErrorCollection([new RequestError(ErrorCode::ExecutionError, 'first')])),
        new ExecutionResult(null, ResultStatus::FAILED, new ErrorCollection([new RequestError(ErrorCode::ExecutionError, 'second')]), exception: new LogicException('second')),
    ];
    $client->executorOverride = $executor;
    $result = new PageRequest()->setClient($client)->paginate()->withConcurrency(2)->all();
    expect($result->exception)->toBeNull()->and($result->nested)->toBe($executor->overrides)
        ->and($result->errors->first()->context['page'])->toBe(2);
});

it('доставляет итоговый FAILED пользовательской фабрике один раз на обоих входах', function (bool $async): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('denied', 403)]);
    $factory = new RecordingFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pagination.test', throwOnErrors: true, resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory)), $transport);
    $request = new PageRequest()->setClient($client);
    expect(fn () => $async
        ? $request->rules(PaginationRule::pages(3, FailStrategy::Partial, 2))->sendAsync()->wait()
        : $request->paginate()->withConcurrency(2)->withFailStrategy(FailStrategy::Partial)->pages(3))
        ->toThrow(ProviderFailure::class);
    expect($factory->results)->toHaveCount(1)->and($factory->results[0])->toBeInstanceOf(PaginatedResult::class)
        ->and($factory->results[0]->nested)->toHaveCount(3);
})->with([false, true]);
