<?php

declare(strict_types=1);

use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Support\ExecutionDelivery;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\BatchConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Execution\ExecutionErrorFactory;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Diagnostics\ExecutionScope;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\Requests\BinaryUploadRequest;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\Tests\Stubs\Requests\TraceOverrideRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;
use ApiSutra\Tests\Stubs\Tracing\FinalMetaExtractor;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;

it('сохраняет идентичность каждого запуска независимо от logger debug и способа доставки', function (bool $debug, bool $logging, bool $async) {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake([SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'A'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', debug: $debug, logger: $logging ? $logger : null), $transport);
    $request = (new SimpleGetRequest('q'))->withTraceId('root');
    $a = ($async ? $client->sendAsync($request)->wait() : $client->send($request))->raw();
    $b = ($async ? $client->sendAsync($request)->wait() : $client->send($request))->raw();
    expect($a->traceId)->toBe('root')->and($a->trace->traceId)->toBe('root')
        ->and($b->trace->executionId)->not->toBe($a->trace->executionId)
        ->and($a->trace->parentExecutionId)->toBeNull()
        ->and($a->debug !== null)->toBe($debug)
        ->and(array_column($a->audit, 'stage'))->toBe([PipelineStage::Started, PipelineStage::HttpRequest, PipelineStage::HttpResponse, PipelineStage::Completed]);
    foreach ($a->audit as $event) {
        expect($event->trace)->toBe($a->trace)->and($event->context['event'])->toBe($event->stage->value);
    }
    expect($a->localized(new LocalizationConfig('ru'))->trace)->toBe($a->trace);
    if ($logging) {
        $terminals = array_values(array_filter($logger->records, fn (array $r): bool => $r['context']['event'] === 'completed'));
        expect($terminals)->toHaveCount(2)->and($terminals[0]['context']['executionId'])->toBe($a->trace->executionId);
    }
})->with([false, true])->with([false, true])->with([false, true]);

it('сохраняет приоритет overrides и сбрасывает только default клиента', function () {
    $transport = new MockTransport();
    $transport->fake([TraceOverrideRequest::class => MockResponse::success(['id' => 1, 'name' => 'A']), SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'A'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test'), $transport);
    $client->setTraceId('client');
    expect($client->send((new TraceOverrideRequest('request'))->withTraceId('runtime'))->raw()->traceId)->toBe('runtime')
        ->and($client->send(new TraceOverrideRequest('request'))->raw()->traceId)->toBe('request')
        ->and($client->send(new SimpleGetRequest('q'))->raw()->traceId)->toBe('client');
    $client->clearTraceId();
    $a = $client->send(new SimpleGetRequest('q'))->raw();
    $b = $client->send(new SimpleGetRequest('q'))->raw();
    expect($a->traceId)->not->toBe('client')->and($b->traceId)->not->toBe($a->traceId);
});

it('изолирует падение logger от HTTP и ошибки запроса', function (string $failOn, bool $throw, bool $success) {
    $logger = new MemoryLogger($failOn);
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake([SimpleGetRequest::class => $success ? MockResponse::success(['id' => 1, 'name' => 'A']) : MockResponse::make(['error' => 'failure'], 404)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger, logLevel: 'debug', debug: true, throwOnErrors: $throw), $transport);
    $caught = null;
    $result = null;
    try {
        $result = $client->send(new SimpleGetRequest('q'))->raw();
    } catch (Throwable $error) {
        $caught = $error;
    }
    if ($throw && !$success) {
        expect($caught)->not->toBeNull()->and($caught->getMessage())->not->toBe('Synthetic logger failure');
    } else {
        expect($caught)->toBeNull()->and($result->isSuccess())->toBe($success);
        expect($result->audit[array_key_last($result->audit)]->stage)->toBe($success ? PipelineStage::Completed : PipelineStage::Failed);
    }
    expect($transport->getRecorded())->toHaveCount(1);
    $terminals = array_filter($logger->records, fn (array $r): bool => in_array($r['context']['event'], ['completed', 'failed'], true));
    expect($terminals)->toHaveCount(1);
})->with(['Request started', 'Request completed', 'Request failed', '*'])->with([false, true])->with([false, true]);

it('завершает scope один раз и измеряет время монотонно', function () {
    $clock = new VirtualClock();
    $logger = new MemoryLogger();
    $scope = new ExecutionScope(ExecutionTrace::create('root'), new AuditLogger(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger)), $clock);
    $scope->start();
    $scope->start();
    $clock->milliseconds += 27;
    $clock->wallTime -= 3600;
    $result = new ExecutionResult([], ResultStatus::SUCCESS, new ErrorCollection([]));
    $first = $scope->finish($result);
    $clock->milliseconds += 100;
    expect($scope->finish($result))->toBe($first)->and($first->audit)->toHaveCount(2)
        ->and($first->audit[1]->duration)->toBe(27.0)
        ->and($first->audit[1]->timestamp)->toBeLessThan($first->audit[0]->timestamp)
        ->and($logger->records)->toHaveCount(2);
});

it('допускает ручной legacy результат и отвергает противоречие trace', function () {
    $legacy = new ExecutionResult([], ResultStatus::SUCCESS, new ErrorCollection([]), traceId: 'legacy');
    expect($legacy->trace)->toBeNull()->and($legacy->traceId)->toBe('legacy');
    expect(fn () => new ExecutionResult([], ResultStatus::SUCCESS, new ErrorCollection([]), traceId: 'legacy', trace: ExecutionTrace::create('other')))
        ->toThrow(ConfigurationException::class);
});

it('сохраняет полный snapshot ошибки каждого batch pool dispatch', function (string $executor, bool $throw) {
    $logger = new MemoryLogger();
    $exception = new RuntimeException('same failure object');
    $transport = new MockTransport();
    $transport->fake([SimpleGetRequest::class => static fn () => throw $exception]);
    $factory = new RecordingFactory(fallback: true);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger, debug: true, throwOnErrors: $throw, resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory)), $transport);
    $requests = [(new SimpleGetRequest('q'))->withTraceId('shared'), (new SimpleGetRequest('q'))->withTraceId('shared')];
    $result = ExecutionDelivery::result(fn () => $executor === 'batch'
        ? $client->batch($requests, new BatchConfig(mode: ExecutionMode::Parallel))->send()
        : $client->pool($requests)->send(), $throw, $factory);
    expect($result->nested)->not->toBeEmpty();
    foreach ($result->nested as $child) {
        expect($child->traceId)->toBe('shared')->and($child->trace)->not->toBeNull()
            ->and($child->debug)->not->toBeNull()->and($child->exception)->toBe($exception)
            ->and(array_column($child->audit, 'stage'))->toBe([PipelineStage::Started, PipelineStage::HttpRequest, PipelineStage::HttpResponse, PipelineStage::Failed]);
    }
    if (count($result->nested) > 1) {
        expect($result->nested[0]->trace->executionId)->not->toBe($result->nested[1]->trace->executionId);
    }
})->with(['batch', 'pool'])->with([false, true]);

it('изолирует ошибку построения диагностического контекста и не вызывает fallback sink', function () {
    $logger = new MemoryLogger();
    $audit = new AuditLogger(new ClientConfig(baseUrl: 'https://trace.test', logger: $logger));
    $audit->log('error', 'Failure', static fn (): array => throw new Error('diagnostic formatting failure'));
    expect($logger->records)->toBe([]);
});

it('не смешивает захваты перекрывающихся вызовов одного request и Throwable', function () {
    $failure = new RuntimeException('shared exception');
    $transport = new MockTransport();
    $transport->fake([SimpleGetRequest::class => static function () use ($failure): MockResponse {
        Fiber::suspend();
        throw $failure;
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test'), $transport);
    $request = (new SimpleGetRequest('q'))->withTraceId('fiber');
    $a = new Fiber(fn () => $client->send($request)->raw());
    $b = new Fiber(fn () => $client->send($request)->raw());
    $a->start();
    $b->start();
    $a->resume();
    $b->resume();
    $first = $a->getReturn();
    $second = $b->getReturn();
    expect($first->exception)->toBe($failure)->and($second->exception)->toBe($failure)
        ->and($first->trace->executionId)->not->toBe($second->trace->executionId);
    foreach ([$first, $second] as $result) {
        foreach ($result->audit as $event) {
            expect($event->trace)->toBe($result->trace);
        }
    }
});

it('ошибка meta или поздний deadline дают единственный failed после HTTP', function (bool $deadline, bool $throw) {
    $logger = new MemoryLogger();
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake([SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'A'])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://trace.test',
        logger: $logger,
        throwOnErrors: $throw,
        retry: new RetryConfig(totalTimeoutMs: 10),
        resultMetaExtractor: new FinalMetaExtractor($deadline ? $clock : null),
    ), $transport, $clock, $clock);
    $result = null;
    $exception = null;
    try {
        $result = $client->send(new SimpleGetRequest('q'))->raw();
        $exception = $result->exception;
    } catch (Throwable $error) {
        $exception = $error;
    }
    expect($exception)->toBeInstanceOf($deadline ? ExecutionDeadlineException::class : RuntimeException::class);
    $terminals = array_values(array_filter($logger->records, fn (array $r): bool => in_array($r['context']['event'], ['completed', 'failed'], true)));
    expect($terminals)->toHaveCount(1)->and($terminals[0]['context']['event'])->toBe('failed')->and($transport->getRecorded())->toHaveCount(1);
    if ($result !== null) {
        expect($result->response->status)->toBe(200)->and($result->isFailed())->toBeTrue();
    }
})->with([false, true])->with([false, true]);

it('явный trace ребёнка сохраняет связь с родителем другого trace', function () {
    $transport = new MockTransport();
    $transport->fake([TraceOverrideRequest::class => MockResponse::success([])]);
    $config = new ClientConfig(baseUrl: 'https://trace.test');
    $client = new TestClient($config, $transport);
    $client->setTraceId('client');
    $parent = new PipelineContext(new SimpleGetRequest('parent'), $config, 'parent');
    $result = $client->sendInContext(new TraceOverrideRequest('child'), $parent, RequestRole::Nested)->raw();
    expect($result->traceId)->toBe('child')->and($result->trace->parentExecutionId)->toBe($parent->trace->executionId);
});

it('retry нумерует реальные отправки внутри одного lifecycle', function () {
    $transport = new MockTransport();
    $transport->fake([SimpleGetRequest::class => MockResponse::sequence([MockResponse::serverError(), MockResponse::success(['id' => 1, 'name' => 'A'])])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false)), $transport);
    $result = $client->send(new SimpleGetRequest('q'))->raw();
    $requests = array_values(array_filter($result->audit, fn ($event) => $event->stage === PipelineStage::HttpRequest));
    expect(array_column(array_column($requests, 'context'), 'attempt'))->toBe([1, 2])
        ->and(array_column($result->audit, 'stage'))->toBe([
            PipelineStage::Started, PipelineStage::HttpRequest, PipelineStage::HttpResponse,
            PipelineStage::HttpRequest, PipelineStage::HttpResponse, PipelineStage::Completed,
        ]);
});

it('fallback сохраняет явный trace и не читает старый context request', function () {
    $request = new SimpleGetRequest('q');
    $transport = new MockTransport();
    $transport->fake([SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'A'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test'), $transport);
    $client->setTraceId('old-client');
    $client->send($request)->raw();
    $error = new RuntimeException('foreign client failure');
    $factory = new ExecutionErrorFactory();
    $fallback = $factory->buildExceptionResult($request, $error);
    expect($fallback->traceId)->toBeNull()->and($fallback->audit)->toBe([]);
    $explicit = $factory->buildExceptionResult($request->withTraceId('explicit'), $error);
    expect($explicit->traceId)->toBe('explicit')->and($explicit->trace)->toBeNull()->and($explicit->audit)->toBe([]);
});

it('исключение callback не заменяется успешным capture', function () {
    $transport = new MockTransport();
    $transport->fake([SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'A'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test'), $transport);
    $failure = new RuntimeException('Callback failed');
    $pool = $client->pool([new SimpleGetRequest('q')])->withResponseHandler(static fn () => throw $failure);
    expect(fn () => $pool->send())->toThrow($failure);
});

it('сбой диагностического sink не прерывает binary upload', function () {
    $logger = new MemoryLogger('*');
    $transport = new MockTransport();
    $transport->fake([BinaryUploadRequest::class => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://trace.test', debug: true, logger: $logger, logLevel: 'debug'), $transport);
    $result = $client->send(new BinaryUploadRequest(FileInput::fromContent('payload', 'fixture.bin')))->raw();
    expect($result->isSuccess())->toBeTrue()->and($transport->getRecorded())->toHaveCount(1)
        ->and($logger->records)->not->toBeEmpty();
});
