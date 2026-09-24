<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\PoolConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Enums\Execution\PoolTerminationReason;
use ApiSutra\Exceptions\ControlFlow\ExecutionCancelledException;
use ApiSutra\Exceptions\Execution\PoolConsumptionException;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Async\ProbeRequest;
use ApiSutra\Tests\Stubs\Core\TestHttpClient;
use ApiSutra\Tests\Stubs\Execution\DeferredExecutor;
use ApiSutra\Tests\Stubs\Execution\ExecutorClient;
use ApiSutra\Tests\Stubs\Execution\Pool\ControlledExecutor;
use ApiSutra\Tests\Stubs\Execution\Pool\NonRecordingTransport;
use ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\LocalTimeoutServer;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\Transport\MockTransport;
use GuzzleHttp\Promise\CancellationException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\HttpFactory;
use Revolt\EventLoop;

/** @return array{ExecutorClient, MockTransport} */
function poolLifecycleClient(array $options = []): array
{
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    return [new ExecutorClient(new ClientConfig(...array_replace([
        'baseUrl' => 'https://pool.test', 'retry' => new RetryConfig(attempts: 1),
    ], $options)), $transport), $transport];
}

it('читает только свободное окно, следующий элемент требует завершения предыдущего', function (): void {
    [$client] = poolLifecycleClient();
    $executor = new ControlledExecutor($client->execution());
    $client->executorOverride = $executor;
    $checkpoint = new Promise();
    $read = $delivered = 0;
    $source = static function () use (&$read, $checkpoint): Generator {
        while (true) {
            $read++;
            if ($read === 3) {
                $checkpoint->resolve(null);
            }
            yield 'repeated-key' => new ProbeRequest();
        }
    };
    $promise = $client->pool($source(), 2)->withResponseHandler(function () use (&$delivered): void {
        $delivered++;
    })->consumeAsync();
    expect($read)->toBe(2)->and($executor->issued)->toBe(2)->and($delivered)->toBe(0);
    $executor->complete(1);
    (new GuzzlePromiseBridge())->await($checkpoint);
    expect($read)->toBe(3)->and($executor->issued)->toBe(3)->and($delivered)->toBe(1);
    $promise->cancel();
    expect(fn () => $promise->wait())->toThrow(CancellationException::class);
    EventLoop::run();
    expect($executor->cancelled)->toBe(2)->and($read)->toBe(3)->and($delivered)->toBe(1);
});

it('callback failure завершает активное окно и не запускает следующие элементы', function (string $kind): void {
    [$client] = poolLifecycleClient();
    $executor = new DeferredExecutor($client->execution());
    $client->executorOverride = $executor;
    $cause = $kind === 'cancellation-class' ? new ExecutionCancelledException() : new LogicException('callback');
    $calls = 0;
    try {
        $client->pool([new ProbeRequest(), new ProbeRequest(), new ProbeRequest()], 2)
            ->withResponseHandler(function () use (&$calls, $cause): void {
                $calls++;
                throw $cause;
            })->consume();
        test()->fail('Ожидалась авария обработчика');
    } catch (PoolConsumptionException $error) {
        expect($error->getPrevious())->toBe($cause)->and($error->summary->terminationReason)->toBe(PoolTerminationReason::HandlerFailed)
            ->and($error->summary->total)->toBe(2)->and($error->summary->successful)->toBe(2)
            ->and($error->summary->firstFailedIndex)->toBeNull();
    }
    expect($calls)->toBe(1)->and($executor->issued)->toBe(2)->and($executor->completed)->toBe(2);
})->with(['ordinary', 'cancellation-class']);

it('нарушение executor не становится результатом HTTP и сохраняет started', function (string $mode): void {
    [$client] = poolLifecycleClient();
    $executor = new DeferredExecutor($client->execution());
    $cause = new LogicException('executor');
    $executor->failure = $mode === 'rejection' ? $cause : null;
    $executor->invalidResult = $mode === 'invalid';
    $client->executorOverride = $executor;
    try {
        $client->pool([new ProbeRequest(), new ProbeRequest(), new ProbeRequest()], 2)->consume();
        test()->fail('Ожидалась авария executor');
    } catch (PoolConsumptionException $error) {
        expect($error->summary->started)->toBe(2)->and($error->summary->total)->toBe(0)
            ->and($error->summary->terminationReason)->toBe(PoolTerminationReason::ExecutorFailed);
        if ($mode === 'rejection') {
            expect($error->getPrevious())->toBe($cause);
        } else {
            expect($error->getPrevious())->toBeInstanceOf(TypeError::class);
        }
    }
    expect($executor->issued)->toBe(2)->and($executor->completed)->toBe(2);
})->with(['rejection', 'invalid']);

it('первая авария источника не заменяется поздним отказом executor', function (): void {
    [$client] = poolLifecycleClient();
    $executor = new DeferredExecutor($client->execution());
    $executor->failure = new LogicException('secondary');
    $client->executorOverride = $executor;
    $cause = new LogicException('primary');
    $source = static function () use ($cause): Generator {
        yield new ProbeRequest();
        throw $cause;
    };
    try {
        $client->pool($source(), 2)->consume();
        test()->fail('Ожидалась авария');
    } catch (PoolConsumptionException $error) {
        expect($error->getPrevious())->toBe($cause)->and($error->summary->started)->toBe(1)
            ->and($error->summary->total)->toBe(0)->and($error->summary->terminationReason)->toBe(PoolTerminationReason::SourceFailed);
    }
    expect($executor->completed)->toBe(1);
});

it('отмена во время завершения начатого не требует итоговой сводки', function (bool $abandon): void {
    [$client] = poolLifecycleClient();
    $executor = new ControlledExecutor($client->execution());
    $client->executorOverride = $executor;
    $checkpoint = new Promise();
    $source = static function () use ($checkpoint): Generator {
        yield new ProbeRequest();
        $checkpoint->resolve(null);
        throw new LogicException('source');
    };
    $before = EventLoop::getIdentifiers();
    $promise = $client->pool($source(), 2)->consumeAsync();
    (new GuzzlePromiseBridge())->await($checkpoint);
    expect($executor->issued)->toBe(1)->and($promise->getState())->toBe('pending');
    if ($abandon) {
        unset($promise);
        gc_collect_cycles();
    } else {
        $promise->cancel();
        expect(fn () => $promise->wait())->toThrow(CancellationException::class);
    }
    EventLoop::run();
    gc_collect_cycles();
    expect($executor->cancelled)->toBe(1)->and($executor->completed)->toBe(0)
        ->and(EventLoop::getIdentifiers())->toBe($before);
})->with([false, true]);

it('удержанный генератор не дочитывается ради finally', function (): void {
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pool.test', pool: new PoolConfig(stopOnFailure: true)), new NonRecordingTransport(failed: true));
    $closed = false;
    $source = (static function () use (&$closed): Generator {
        try {
            yield new ProbeRequest();
            throw new LogicException('Не дочитывать');
        } finally {
            $closed = true;
        }
    })();
    expect($client->pool($source, 1)->consume()->total)->toBe(1)->and($closed)->toBeFalse();
    unset($source);
    gc_collect_cycles();
    expect($closed)->toBeTrue();
});

it('перекрывает реальный HTTP и доставляет по готовности', function (int $concurrency): void {
    $server = new LocalTimeoutServer();
    $client = new TestClient(new ClientConfig(baseUrl: $server->httpUrl), HttpTransport::createDefault());
    $peaks = $order = [];
    $before = EventLoop::getIdentifiers();
    $source = static function (): Generator {
        foreach ([90, 10, 50] as $ms) {
            yield new ProbeRequest($ms);
        }
    };
    $summary = $client->pool($source(), $concurrency)->withResponseHandler(function (ExecutionResult $result) use (&$peaks, &$order): void {
        $peaks[] = $result->data['peak'];
        $order[] = $result->data['delay'];
    })->consume();
    expect($summary->total)->toBe(3)->and(max($peaks))->toBe($concurrency)
        ->and($order)->toBe($concurrency === 1 ? [90, 10, 50] : [10, 50, 90]);
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe($before);
    $server->close();
})->with([1, 2]);

it('sync с одним местом не требует async-capability', function (): void {
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://sync.test', timeout: 0, connectTimeout: 0), new HttpTransport(new TestHttpClient(), $factory, $factory));
    expect($client->pool([(new ProbeRequest())->withRawResponse()], 1)->consume()->successful)->toBe(1);
    $errors = [];
    $summary = $client->pool([new ProbeRequest()], 1)->withResponseHandler(function (ExecutionResult $result) use (&$errors): void {
        $errors[] = $result->errors->first()->code->value;
    })->consumeAsync()->wait();
    expect($summary->failed)->toBe(1)->and($errors)->toBe(['configuration_error']);
});

it('Utils all и вложенные ожидания не смешивают сводки и trace', function (): void {
    [$client] = poolLifecycleClient();
    $ids = [];
    $run = function (string $prefix, int $count) use ($client, &$ids) {
        $source = static function () use ($prefix, $count): Generator {
            for ($i = 0; $i < $count; $i++) {
                yield (new ProbeRequest())->withTraceId($prefix . $i)->withDelay(1);
            }
        };
        return $client->pool($source(), 2)->withResponseHandler(function (ExecutionResult $result) use ($client, &$ids): void {
            expect($result->trace->parentExecutionId)->toBeNull()->and($result->nested)->toBe([]);
            $ids[] = $result->traceId;
            expect($client->sendAsync(new ProbeRequest())->wait()->raw()->isSuccess())->toBeTrue();
        })->consumeAsync();
    };
    $summaries = Utils::all([$run('a', 2), $run('b', 3)])->wait();
    expect(array_column($summaries, 'total'))->toBe([2, 3]);
    sort($ids);
    expect($ids)->toBe(['a0', 'a1', 'b0', 'b1', 'b2']);
});

it('завершённые элементы освобождаются без истории в сводке', function (bool $failed, int $concurrency, bool $async): void {
    $client = new TestClient(new ClientConfig(baseUrl: 'https://memory.test', retry: new RetryConfig(attempts: 1)), new NonRecordingTransport($failed));
    $requests = $results = [];
    $source = static function () use (&$requests): Generator {
        for ($i = 0; $i < 30; $i++) {
            $request = new ProbeRequest();
            $requests[] = WeakReference::create($request);
            yield $request;
        }
    };
    $pool = $client->pool($source(), $concurrency)->withResponseHandler(function (ExecutionResult $result) use (&$results): void {
        $results[] = WeakReference::create($result);
    });
    $summary = $async ? $pool->consumeAsync()->wait() : $pool->consume();
    // Сам Generator источника вправе удерживать последний yield, пока жив builder приложения.
    unset($pool);
    EventLoop::run();
    gc_collect_cycles();
    expect($summary->total)->toBe(30)->and($summary->failed)->toBe($failed ? 30 : 0);
    foreach (array_merge($requests, $results) as $index => $reference) {
        expect($reference->get() === null)->toBeTrue("Удержана ссылка $index");
    }
})->with([false, true])->with([1, 3])->with([false, true]);

it('файл сохранённый callback остаётся читаемым после consume', function (): void {
    [$client, $transport] = poolLifecycleClient();
    $transport->fake(['*' => MockResponse::make('download-content', 200, ['Content-Type' => 'application/octet-stream'])]);
    $stream = null;
    $summary = $client->pool([new ProviderBDownloadRequest('one')], 2)->withResponseHandler(function (ExecutionResult $result) use (&$stream): void {
        $stream = $result->response->stream;
    })->consumeAsync()->wait();
    gc_collect_cycles();
    expect($summary->successful)->toBe(1)->and((string) $stream)->toBe('download-content');
});

it('cache hit учитывается как исполнение и не запускает дополнительный HTTP', function (): void {
    [$client, $transport] = poolLifecycleClient(['cacheConfig' => new CacheConfig(store: new ArrayCache())]);
    $client->send((new ProbeRequest())->withCache())->raw();
    $source = static function (): Generator {
        for ($i = 0; $i < 20; $i++) {
            yield (new ProbeRequest())->withCache();
        }
    };
    $summary = $client->pool($source(), 3)->consumeAsync()->wait();
    expect($summary->started)->toBe(20)->and($summary->successful)->toBe(20)
        ->and($transport->getRecorded())->toHaveCount(1);
});

it('ошибка получения executor не увеличивает started до передачи ему элемента', function (int $concurrency): void {
    $client = new class (new ClientConfig('https://pool.test'), new NonRecordingTransport()) extends AbstractClient {
        public function execution(): ClientExecutorInterface
        {
            throw new LogicException('executor unavailable');
        }
    };
    try {
        $client->pool([new ProbeRequest()], $concurrency)->consume();
        test()->fail('Ожидалась ошибка получения executor');
    } catch (PoolConsumptionException $error) {
        expect($error->summary->started)->toBe(0)->and($error->summary->total)->toBe(0)
            ->and($error->summary->terminationReason)->toBe(PoolTerminationReason::ExecutorFailed)
            ->and($error->getPrevious()->getMessage())->toBe('executor unavailable');
    }
})->with([1, 2]);
