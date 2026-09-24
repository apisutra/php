<?php

declare(strict_types=1);

use ApiSutra\Tests\Stubs\Execution\ExecutorClient;
use ApiSutra\Tests\Stubs\Execution\RecordingExecutor;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Stubs\ResultContract\ProviderFailure;




use ApiSutra\Pagination\Paginator;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Continuation\ContinuationAwaitOptions;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Exceptions\Continuation\ContinuationAwaitException;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\Requests\CompositeAggregateRequest;
use ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;
use ApiSutra\Tests\Stubs\Requests\ContinuationStartRequest;
use ApiSutra\Tests\Stubs\Requests\DependencyTokenRequest;
use ApiSutra\Tests\Stubs\Requests\DependsOnMainRequest;
use ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;
use ApiSutra\Tests\Stubs\Tracing\ParallelCompositeRequest;
use ApiSutra\Tests\Stubs\Tracing\DependencyProcessingRequest;
use ApiSutra\Tests\Stubs\Tracing\TokenExtractor;
use ApiSutra\Transport\MockTransport;

it('сохраняет один lifecycle составного запроса и отдельные идентичности детей', function (string $type, bool $failure) {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake([
        SimpleGetRequest::class => $failure ? MockResponse::notFound() : MockResponse::success(['id' => 1, 'name' => 'A']),
        DependencyTokenRequest::class => $failure ? MockResponse::notFound() : MockResponse::success(['token' => 'synthetic']),
        DependsOnMainRequest::class => MockResponse::success(['ok' => true]),
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger), $transport);
    $request = new $type();
    $request->setClient($client);
    $result = $client->send($request->withTraceId('tree'))->raw();
    expect($result->isFailed())->toBe($failure)->and($result->nested)->not->toBeEmpty();
    $events = array_column($result->audit, 'stage');
    expect(count(array_filter($events, fn ($stage) => $stage === PipelineStage::Started)))->toBe(1)
        ->and($events[array_key_last($events)])->toBe($failure ? PipelineStage::Failed : PipelineStage::Completed);
    foreach ($result->nested as $child) {
        expect($child->traceId)->toBe('tree')->and($child->trace->parentExecutionId)->toBe($result->trace->executionId)
            ->and($child->trace->executionId)->not->toBe($result->trace->executionId);
    }
    $starts = array_filter($logger->records, fn (array $r): bool => $r['context']['event'] === 'started' && $r['context']['executionId'] === $result->trace->executionId);
    expect($starts)->toHaveCount(1);
    if ($type === DependsOnMainRequest::class) {
        expect($transport->getRecorded())->toHaveCount($failure ? 1 : 2);
    }
})->with([CompositeAggregateRequest::class, DependsOnMainRequest::class])->with([false, true]);

it('связывает страницы с корнем пагинации при явном и автоматическом trace', function (bool $explicit, bool $direct) {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake([PaginatedRequest::class => MockResponse::sequence([
        MockResponse::success(['data' => [['id' => 1]], 'meta' => ['page' => 1, 'limit' => 1, 'total' => 2, 'has_more' => true, 'next_cursor' => 'second']]),
        MockResponse::success(['data' => [['id' => 2]], 'meta' => ['page' => 2, 'limit' => 1, 'total' => 2, 'has_more' => false]]),
    ])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger), $transport);
    $request = (new PaginatedRequest())->setClient($client);
    $execution = $explicit ? $request->withTraceId('pages') : $request;
    $result = $direct ? $execution->paginate()->all() : $client->send($execution->rules(PaginationRule::all()))->raw();
    expect($result->nested)->toHaveCount(2)->and($result->trace)->not->toBeNull()
        ->and(array_column($result->audit, 'stage'))->toBe([PipelineStage::Started, PipelineStage::Completed]);
    foreach ($result->nested as $page) {
        expect($page->traceId)->toBe($result->traceId)->and($page->trace->parentExecutionId)->toBe($result->trace->executionId);
    }
    expect($result->nested[0]->trace->executionId)->not->toBe($result->nested[1]->trace->executionId);
    if ($explicit) {
        expect($result->traceId)->toBe('pages');
    }
})->with([false, true])->with([false, true]);

it('завершает ленивый обход при исчерпании и отмечает освобождённый генератор как abandoned', function (bool $stop) {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake([PaginatedRequest::class => MockResponse::success(['data' => [['id' => 1]], 'meta' => ['page' => 1, 'limit' => 1, 'total' => 1, 'has_more' => false]])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger), $transport);
    $paginator = (new PaginatedRequest())->setClient($client)->paginate();
    $generator = $paginator->getIterator();
    expect($logger->records)->toBe([]);
    foreach ($generator as $page) {
        if ($stop) {
            break;
        }
    }
    $parent = $page->trace->parentExecutionId;
    $events = fn (): array => array_values(array_filter($logger->records, fn (array $r): bool => $r['context']['executionId'] === $parent));
    if ($stop) {
        expect($events())->toHaveCount(1);
        unset($generator);
    }
    expect(array_column(array_column($events(), 'context'), 'event'))->toBe(['started', $stop ? 'abandoned' : 'completed'])
        ->and($transport->getRecorded())->toHaveCount(1);
    foreach ($paginator as $again) {
    }
    expect($again->trace->parentExecutionId)->not->toBe($parent);
})->with([false, true]);

it('сохраняет trace старта в await и связывает poll с ожиданием', function (bool $clientDefault, bool $pending) {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake([
        ContinuationStartRequest::class => MockResponse::success($pending ? ['operationToken' => 'secret-job'] : ['data' => ['value' => 'ready']]),
        ContinuationPollRequest::class => MockResponse::sequence([
            MockResponse::success(['operationToken' => 'secret-job']),
            MockResponse::success(['data' => ['value' => 'ready']]),
        ]),
    ]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://trace.test',
        logger: $logger,
        continuationTokenExtractor: new TokenExtractor(),
        defaultContinuationMode: ContinuationMode::Auto
    ), $transport);
    if ($clientDefault) {
        $client->setTraceId('client-default');
    }
    $request = (new ContinuationStartRequest('job'))->withTraceId('start');
    $start = $client->send($request)->raw();
    $outcome = $client->continuation()->resolveFromStartResult($start, $request, options: new ContinuationAwaitOptions(maxAttempts: 2, intervalMs: 0));
    expect($outcome->value->value)->toBe('ready')->and($outcome->trace->traceId)->toBe('start')
        ->and($outcome->trace->parentExecutionId)->toBe($start->trace->executionId)
        ->and(array_column($outcome->audit, 'stage'))->toBe([PipelineStage::Started, PipelineStage::Completed])
        ->and($outcome->lastResult->traceId)->toBe('start');
    if ($pending) {
        expect($outcome->lastResult->trace->parentExecutionId)->toBe($outcome->trace->executionId)
            ->and($outcome->attempts)->toBe(3);
    }
    expect(json_encode($logger->records))->not->toContain('secret-job');
})->with([false, true])->with([false, true]);

it('добавляет идентичность ожидания к ошибке и безопасному логу', function (bool $badShape) {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake([
        ContinuationStartRequest::class => MockResponse::success(['operationToken' => 'secret-job']),
        ContinuationPollRequest::class => MockResponse::success($badShape ? ['data' => ['value' => []]] : ['operationToken' => 'secret-job']),
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger, localization: 'ru', continuationTokenExtractor: new TokenExtractor()), $transport);
    $handle = $client->send((new ContinuationStartRequest('job'))->withTraceId('await'));
    $caught = null;
    try {
        $handle->await(new ContinuationAwaitOptions(maxAttempts: 1, intervalMs: 0));
    } catch (ContinuationAwaitException $exception) {
        $caught = $exception;
    }
    expect($caught)->not->toBeNull()->and($caught->reason)->toBe($badShape ? 'final_hydration_failed' : 'attempts_exhausted')
        ->and($caught->trace->traceId)->toBe('await')->and($caught->trace->parentExecutionId)->toBe($handle->raw()->trace->executionId)
        ->and($caught->lastResult->trace->parentExecutionId)->toBe($caught->trace->executionId);
    $terminals = array_values(array_filter($logger->records, fn (array $r): bool => $r['context']['event'] === 'failed' && $r['context']['executionId'] === $caught->trace->executionId));
    expect($terminals)->toHaveCount(1)->and($terminals[0]['context'])->toMatchArray($caught->logContext())
        ->and(json_encode($terminals))->not->toContain('secret-job');
})->with([false, true]);

it('cached await не создаёт запуск а смена типа создаёт дочернюю конвертацию', function () {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake([ContinuationStartRequest::class => MockResponse::success(['data' => ['value' => 'ready', 'id' => 1, 'name' => 'A']])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger), $transport);
    $handle = $client->send(new ContinuationStartRequest('job'));
    $first = $handle->await();
    $count = count($logger->records);
    expect($handle->await())->toBe($first)->and($logger->records)->toHaveCount($count);
    $last = $logger->records[array_key_last($logger->records)]['context']['executionId'];
    $remapped = $handle->awaitAs(SimpleResponseDto::class);
    expect($remapped->id)->toBe(1)->and($transport->getRecorded())->toHaveCount(1);
    $context = $logger->records[array_key_last($logger->records)]['context'];
    expect($context['parentExecutionId'])->toBe($last)->and($context['executionId'])->not->toBe($last);
});

it('самостоятельное ожидание получает один trace от client default', function () {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake([ContinuationPollRequest::class => MockResponse::sequence([
        MockResponse::success(['operationToken' => 'secret-job']),
        MockResponse::success(['data' => ['value' => 'ready']]),
    ])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger, continuationTokenExtractor: new TokenExtractor()), $transport);
    $client->setTraceId('job');
    $value = $client->continuation()->awaitByToken('secret-job', ContinuationStartRequest::class, new ContinuationAwaitOptions(maxAttempts: 2, intervalMs: 0));
    $starts = array_values(array_filter($logger->records, fn (array $r): bool => $r['context']['event'] === 'started'));
    expect($value->value)->toBe('ready')->and($starts)->toHaveCount(3)
        ->and(array_unique(array_column(array_column($starts, 'context'), 'trace')))->toBe(['job'])
        ->and($starts[0]['context']['parentExecutionId'])->toBeNull()
        ->and($starts[1]['context']['parentExecutionId'])->toBe($starts[0]['context']['executionId'])
        ->and($starts[2]['context']['parentExecutionId'])->toBe($starts[0]['context']['executionId']);
});

it('ошибка cached remap сохраняет прежний outcome и payload без нового HTTP', function () {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake([ContinuationStartRequest::class => MockResponse::success(['data' => ['value' => 'ready']])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger), $transport);
    $handle = $client->send(new ContinuationStartRequest('job'));
    $first = $handle->await();
    expect(fn () => $handle->awaitAs(SimpleResponseDto::class))->toThrow(ContinuationAwaitException::class);
    $count = count($logger->records);
    expect($handle->await())->toBe($first)->and($logger->records)->toHaveCount($count)
        ->and($transport->getRecorded())->toHaveCount(1)
        ->and($handle->raw()->response->json('data.value'))->toBe('ready');
});

it('ручной Paginator с исполнителем не требует клиента и не хранит выданные страницы', function () {
    $references = [];
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::sequence([
        MockResponse::success(['data' => [], 'meta' => ['has_more' => true, 'next_cursor' => 'second']]),
        MockResponse::success(['data' => [], 'meta' => ['has_more' => true, 'next_cursor' => 'third']]),
        MockResponse::success(['data' => [], 'meta' => ['has_more' => false]]),
    ])]);
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://trace.test'), $transport);
    $executor = new RecordingExecutor($client->execution());
    $client->executorOverride = $executor;
    $paginator = new Paginator(new PaginatedRequest(), client: $client);
    foreach ($paginator as $page) {
    }
    unset($page);
    gc_collect_cycles();
    expect($executor->requests)->toHaveCount(3);
    // Сам запрос сохраняет только последний context; история страниц освобождается.
    foreach (array_slice($executor->results, 0, -1) as $reference) {
        expect($reference->get())->toBeNull();
    }
});

it('ошибка обработки зависимостей сохраняет их результаты и не отправляет основной HTTP', function () {
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake([DependencyTokenRequest::class => MockResponse::success(['token' => 'synthetic'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test'), $transport);
    $request = (new DependencyProcessingRequest())->setClient($client);
    $result = $client->send($request)->raw();
    expect($result->isFailed())->toBeTrue()->and($result->nested)->toHaveCount(1)
        ->and($result->nested[0]->isSuccess())->toBeTrue()
        ->and($result->nested[0]->trace->parentExecutionId)->toBe($result->trace->executionId)
        ->and(array_column($result->audit, 'stage'))->toBe([PipelineStage::Started, PipelineStage::Failed])
        ->and($transport->getRecorded())->toHaveCount(1);
});

it('выдаёт агрегированную ошибку после завершения при throwOnErrors', function (bool $composite) {
    $transport = new MockTransport();
    $transport->fake([
        SimpleGetRequest::class => MockResponse::notFound(),
        PaginatedRequest::class => MockResponse::success(['data' => [], 'meta' => ['has_more' => true, 'next_cursor' => null]]),
    ]);
    $factory = new RecordingFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', throwOnErrors: true, resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory)), $transport);
    $request = $composite ? new ParallelCompositeRequest() : new PaginatedRequest();
    $request->setClient($client);
    $execution = $composite ? $request : $request->rules(PaginationRule::all());
    expect(fn () => $client->send($execution))->toThrow(ProviderFailure::class);
    expect($factory->results)->toHaveCount(1);
    $result = $factory->results[0];
    expect($result->isFailed())->toBeTrue()->and($result->nested)->not->toBeEmpty()
        ->and(array_column($result->audit, 'stage'))->toBe([PipelineStage::Started, PipelineStage::Failed]);
})->with([false, true]);
