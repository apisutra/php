<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Stubs\ResultContract\ProviderFailure;
use ApiSutra\Continuation\ContinuationAwaitOptions;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Execution\Async\AsyncRuntime;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\LockAwareAuthenticator;
use ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;
use ApiSutra\Tests\Stubs\Requests\ContinuationStartRequest;
use ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;
use ApiSutra\Tests\Stubs\Tracing\ParallelCompositeRequest;
use ApiSutra\Tests\Stubs\Tracing\TokenExtractor;
use ApiSutra\Timing\ExecutionDeadline;
use ApiSutra\Transport\MockTransport;
use GuzzleHttp\Promise\CancellationException;
use GuzzleHttp\Promise\Utils;
use Revolt\EventLoop;

$assertLifecycle = static function (ExecutionResult $result, MemoryLogger $logger): void {
    $stages = array_column($result->audit, 'stage');
    expect(array_filter($stages, static fn (PipelineStage $stage): bool => $stage === PipelineStage::Started))->toHaveCount(1);
    expect(array_filter($stages, static fn (PipelineStage $stage): bool => in_array($stage, [PipelineStage::Completed, PipelineStage::Failed, PipelineStage::Abandoned], true)))->toHaveCount(1);
    foreach ($result->audit as $event) {
        expect($event->trace)->toBe($result->trace);
    }
    $logs = array_values(array_filter(array_column($logger->records, 'context'), static fn (array $context): bool => ($context['executionId'] ?? null) === $result->trace->executionId));
    expect(array_column($logs, 'trace'))->each->toBe($result->traceId);
    expect(array_column($logs, 'parentExecutionId'))->each->toBe($result->trace->parentExecutionId);
    expect(array_filter($logs, static fn (array $context): bool => $context['event'] === 'started'))->toHaveCount(1);
    expect(array_filter($logs, static fn (array $context): bool => in_array($context['event'], ['completed', 'failed', 'abandoned'], true)))->toHaveCount(1);
};

it('сохраняет trace активных вызовов при смене default и повторном ожидании одного handle', function () use ($assertLifecycle): void {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake([SimpleGetRequest::class => static function (): MockResponse {
        AsyncTask::current()->runtime->sleep(5);
        return MockResponse::success(['id' => 1, 'name' => 'A']);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger, debug: true, logLevel: 'debug'), $transport);
    $request = new SimpleGetRequest('same');
    $client->setTraceId('first');
    $first = $client->sendAsync($request);
    $client->setTraceId('second');
    $second = $client->sendAsync($request);
    $client->clearTraceId();
    $third = $client->sendAsync($request);
    $runtime = new AsyncRuntime();
    $waiters = Utils::all([
        $runtime->start(fn () => $first->wait()->raw()),
        $runtime->start(fn () => $first->wait()->resolved()),
    ])->wait();
    $results = [$first->wait()->raw(), $second->wait()->raw(), $third->wait()->raw()];
    expect($results[0]->traceId)->toBe('first')->and($results[1]->traceId)->toBe('second')
        ->and($results[2]->traceId)->not->toBeIn(['first', 'second'])
        ->and($waiters[0])->toBe($results[0])->and($transport->getRecorded())->toHaveCount(3);
    expect(array_unique(array_map(static fn (ExecutionResult $result): string => $result->trace->executionId, $results)))->toHaveCount(3);
    foreach ($results as $result) {
        $assertLifecycle($result, $logger);
        expect($result->trace->parentExecutionId)->toBeNull()->and($result->debug->duration)->toBeGreaterThanOrEqual(5.0);
    }
});

it('разделяет деревья конкурентных composite и страниц после приостановки', function (bool $pages) use ($assertLifecycle): void {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake([
        SimpleGetRequest::class => static function (): MockResponse {
            AsyncTask::current()->runtime->sleep(5);
            return MockResponse::success(['id' => 1, 'name' => 'A']);
        },
        PaginatedRequest::class => static function (): MockResponse {
            AsyncTask::current()->runtime->sleep(5);
            return MockResponse::success(['data' => [['id' => 1]], 'meta' => ['page' => 1, 'limit' => 1, 'total' => 2, 'has_more' => true, 'next_cursor' => 'second']]);
        },
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger), $transport);
    $request = $pages ? (new PaginatedRequest())->setClient($client)->rules(PaginationRule::pages(2)) : (new ParallelCompositeRequest())->setClient($client);
    $a = $client->sendAsync($request->withTraceId('a'));
    $b = $client->sendAsync($request->withTraceId('b'));
    $ids = [];
    foreach ([$a->wait()->raw(), $b->wait()->raw()] as $result) {
        $assertLifecycle($result, $logger);
        expect($result->isSuccess())->toBeTrue()->and($result->nested)->toHaveCount(2);
        $ids[] = $result->trace->executionId;
        foreach ($result->nested as $child) {
            $assertLifecycle($child, $logger);
            expect($child->traceId)->toBe($result->traceId)->and($child->trace->parentExecutionId)->toBe($result->trace->executionId);
            $ids[] = $child->trace->executionId;
        }
    }
    expect(array_unique($ids))->toHaveCount(6);
})->with([false, true]);

it('связывает общий refresh только с владельцем без переноса trace ожидающего запроса', function () use ($assertLifecycle): void {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $refreshes = 0;
    $transport->fake([
        RefreshTokenRequest::class => static function () use (&$refreshes): MockResponse {
            $refreshes++;
            AsyncTask::current()->runtime->sleep(5);
            return MockResponse::success(['token' => 'fresh']);
        },
        SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'A']),
    ]);
    $auth = new LockAwareAuthenticator();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger, auth: $auth), $transport);
    $a = $client->sendAsync((new SimpleGetRequest('a'))->withTraceId('owner'));
    $b = $client->sendAsync((new SimpleGetRequest('b'))->withTraceId('waiter'));
    $owner = $a->wait()->raw();
    $waiter = $b->wait()->raw();
    expect($owner->nested)->toHaveCount(1)->and($waiter->nested)->toBe([])->and($refreshes)->toBe(1);
    $refresh = $owner->nested[0];
    expect($refresh->traceId)->toBe('owner')->and($refresh->trace->parentExecutionId)->toBe($owner->trace->executionId)
        ->and($waiter->traceId)->toBe('waiter')->and($waiter->trace->parentExecutionId)->toBeNull();
    foreach ([$owner, $waiter, $refresh] as $result) {
        $assertLifecycle($result, $logger);
    }
});

it('сохраняет номера попыток и единственный terminal при retry и deadline', function (bool $deadline) use ($assertLifecycle): void {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake([SimpleGetRequest::class => MockResponse::sequence([
        MockResponse::serverError(), MockResponse::success(['id' => 1, 'name' => 'A']),
    ])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger, retry: new RetryConfig(attempts: 2, baseDelay: 50, jitter: false)), $transport);
    $request = (new SimpleGetRequest('q'))->withTraceId('retry');
    if ($deadline) {
        $request = $request->withDeadline(ExecutionDeadline::afterMs(30));
    }
    $handle = $client->sendAsync($request);
    $result = $handle->wait()->raw();
    $assertLifecycle($result, $logger);
    expect($handle->wait()->raw())->toBe($result)->and($result->isFailed())->toBe($deadline);
    $attempts = array_values(array_filter($result->audit, static fn ($event): bool => $event->stage === PipelineStage::HttpRequest));
    expect(array_column(array_column($attempts, 'context'), 'attempt'))->toBe($deadline ? [1] : [1, 2]);
    if ($deadline) {
        expect($result->errors->first()->context['reason'])->toBe('execution_deadline_exceeded');
    }
})->with([false, true]);

it('завершает все начатые scopes при отмене или освобождении async операции', function (string $kind, bool $discard): void {
    EventLoop::run();
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake(['*' => static function (): MockResponse {
        AsyncTask::current()->runtime->sleep(60000);
        return MockResponse::success(['id' => 1, 'name' => 'A']);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger), $transport);
    $request = match ($kind) {
        'composite' => (new ParallelCompositeRequest())->setClient($client),
        'pages' => (new PaginatedRequest())->setClient($client)->rules(PaginationRule::pages(2)),
        default => (new SimpleGetRequest('q'))->withDelay(60000),
    };
    $handle = $client->sendAsync($request->withTraceId('stopped'));
    if (!$discard) {
        $handle->cancel();
        expect(fn () => $handle->wait()->raw())->toThrow(CancellationException::class);
        EventLoop::run();
    }
    unset($handle, $request);
    gc_collect_cycles();
    $byExecution = [];
    foreach (array_column($logger->records, 'context') as $context) {
        if (in_array($context['event'], ['started', 'completed', 'failed', 'abandoned'], true)) {
            $byExecution[$context['executionId']][] = $context;
        }
    }
    expect($byExecution)->not->toBeEmpty();
    foreach ($byExecution as $events) {
        expect(array_column($events, 'event'))->toBe(['started', $discard ? 'abandoned' : 'failed']);
        if ($discard) {
            expect($events[1]['reason'])->toBe('execution_cancelled');
        }
    }
    expect(EventLoop::getIdentifiers())->toBe([]);
})->with(['delay', 'composite', 'pages'])->with([false, true]);

it('завершает освобождённое ожидание continuation без нового poll', function (): void {
    EventLoop::run();
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake([
        ContinuationStartRequest::class => MockResponse::success(['operationToken' => 'job']),
        ContinuationPollRequest::class => MockResponse::success(['operationToken' => 'job']),
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger, continuationTokenExtractor: new TokenExtractor()), $transport);
    $handle = $client->send(new ContinuationStartRequest('job'));
    $promise = (new AsyncRuntime())->start(fn () => $handle->await(new ContinuationAwaitOptions(maxAttempts: 2, intervalMs: 60000)));
    $started = array_values(array_filter(array_column($logger->records, 'context'), static fn (array $context): bool => $context['event'] === 'started'));
    $awaitId = $started[1]['executionId'];
    unset($promise);
    gc_collect_cycles();
    $events = array_values(array_filter(array_column($logger->records, 'context'), static fn (array $context): bool => $context['executionId'] === $awaitId));
    expect(array_column($events, 'event'))->toBe(['started', 'abandoned'])
        ->and($events[1]['reason'])->toBe('execution_cancelled')->and($transport->getRecorded())->toHaveCount(2)
        ->and(EventLoop::getIdentifiers())->toBe([]);
});

it('не дублирует failed при двух ожидающих rejection и сохраняет снимок ошибки', function () use ($assertLifecycle): void {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $failure = new RuntimeException('Ошибка тестового транспорта');
    $transport->fake([SimpleGetRequest::class => static function () use ($failure): never {
        AsyncTask::current()->runtime->sleep(5);
        throw $failure;
    }]);
    $factory = new RecordingFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger, throwOnErrors: true, resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory)), $transport);
    $handle = $client->sendAsync((new SimpleGetRequest('q'))->withTraceId('rejected'));
    $runtime = new AsyncRuntime();
    $a = $runtime->start(fn () => $handle->wait()->raw());
    $b = $runtime->start(fn () => $handle->wait()->resolved());
    expect(fn () => $a->wait())->toThrow(ProviderFailure::class);
    expect(fn () => $b->wait())->toThrow(ProviderFailure::class);
    expect($factory->results)->toHaveCount(1)->and($factory->results[0]->exception)->toBe($failure)
        ->and($transport->getRecorded())->toHaveCount(1);
    $assertLifecycle($factory->results[0], $logger);
});

it('связывает конкурентные continuation polls со своим ожиданием и стартом', function () use ($assertLifecycle): void {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $polls = [];
    $transport->fake([
        ContinuationStartRequest::class => static fn (ContinuationStartRequest $request): MockResponse => MockResponse::success(['operationToken' => $request->id]),
        ContinuationPollRequest::class => static function (ContinuationPollRequest $request) use (&$polls): MockResponse {
            $count = $polls[$request->operationToken] = ($polls[$request->operationToken] ?? 0) + 1;
            AsyncTask::current()->runtime->sleep(5);
            return MockResponse::success($count === 1 ? ['operationToken' => $request->operationToken] : ['data' => ['value' => $request->operationToken]]);
        },
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger, continuationTokenExtractor: new TokenExtractor()), $transport);
    $runtime = new AsyncRuntime();
    $starts = $awaits = [];
    foreach (['first', 'second'] as $trace) {
        $request = new ContinuationStartRequest($trace);
        $starts[$trace] = $start = $client->send($request->withTraceId($trace))->raw();
        $awaits[$trace] = $runtime->start(fn () => $client->continuation()->resolveFromStartResult($start, $request, options: new ContinuationAwaitOptions(maxAttempts: 3, intervalMs: 5)));
    }
    foreach (Utils::all($awaits)->wait() as $trace => $outcome) {
        expect($outcome->value->value)->toBe($trace)->and($outcome->trace->traceId)->toBe($trace)
            ->and($outcome->trace->parentExecutionId)->toBe($starts[$trace]->trace->executionId)
            ->and($outcome->lastResult->trace->parentExecutionId)->toBe($outcome->trace->executionId);
        $assertLifecycle($outcome->lastResult, $logger);
        $logs = array_values(array_filter(array_column($logger->records, 'context'), static fn (array $context): bool => $context['executionId'] === $outcome->trace->executionId));
        expect(array_column($logs, 'event'))->toBe(['started', 'completed']);
    }
    expect($polls)->toBe(['first' => 2, 'second' => 2]);
});

it('фиксирует failed явной отмены сразу без запуска loop и повторного terminal', function (): void {
    $logger = new MemoryLogger();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger), new MockTransport());
    $promise = $client->sendAsync((new SimpleGetRequest('q'))->withDelay(60000));
    $promise->cancel();
    expect(array_column(array_column($logger->records, 'context'), 'event'))->toBe(['started', 'failed']);
    expect($logger->records[1]['context']['reason'])->toBe('execution_cancelled');
    expect(fn () => $promise->wait())->toThrow(CancellationException::class);
    EventLoop::run();
    expect(array_column(array_column($logger->records, 'context'), 'event'))->toBe(['started', 'failed']);
});
