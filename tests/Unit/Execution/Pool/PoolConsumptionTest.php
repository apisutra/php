<?php

declare(strict_types=1);

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Config\PoolConfig;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Execution\PoolTerminationReason;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Configuration\ExceptionFactoryException;
use ApiSutra\Exceptions\Execution\PoolConsumptionException;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\PoolSummary;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Async\ProbeRequest;
use ApiSutra\Tests\Stubs\Execution\DeferredExecutor;
use ApiSutra\Tests\Stubs\Execution\ExecutorClient;
use ApiSutra\Tests\Stubs\Execution\Pool\FailingIterator;
use ApiSutra\Tests\Stubs\ResultContract\ProviderFailure;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Transport\MockTransport;

/** @return array{ExecutorClient, MockTransport} */
function poolConsumptionClient(array $options = []): array
{
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $client = new ExecutorClient(new ClientConfig(...array_replace([
        'baseUrl' => 'https://pool.test',
        'retry' => new RetryConfig(attempts: 1),
    ], $options)), $transport);
    return [$client, $transport];
}

it('сводка совпадает с коллекцией для всех сочетаний статусов', function (array $statuses, bool $async): void {
    [$client] = poolConsumptionClient();
    $executor = new DeferredExecutor($client->execution(), reverse: true);
    $executor->overrides = array_map(static fn (string $status): ExecutionResult => new ExecutionResult(null, ResultStatus::from($status), new ErrorCollection([])), $statuses);
    $client->executorOverride = $executor;
    $source = static function () use ($statuses): Generator {
        foreach ($statuses as $status) {
            yield 'same-key' => new ProbeRequest();
        }
    };
    $pool = $client->pool($source(), 3);
    $summary = $async ? $pool->consumeAsync()->wait() : $pool->consume();
    $expected = ResultCollection::make($executor->overrides)->summarize();
    expect($summary)->toBeInstanceOf(PoolSummary::class)
        ->and($summary->started)->toBe(count($statuses))
        ->and($summary->terminationReason)->toBe(PoolTerminationReason::SourceExhausted);
    foreach (['total', 'successful', 'failed', 'partial', 'status'] as $field) {
        expect($summary->$field)->toBe($expected->$field);
    }
    $first = array_search(ResultStatus::FAILED->value, $statuses, true);
    expect($summary->firstFailedIndex)->toBe($first === false ? null : $first);
})->with([
    'пустой' => [[]],
    'успех' => [[ResultStatus::SUCCESS->value, ResultStatus::SUCCESS->value]],
    'отказы' => [[ResultStatus::FAILED->value, ResultStatus::FAILED->value]],
    'смешанный' => [[ResultStatus::SUCCESS->value, ResultStatus::FAILED->value, ResultStatus::FAILED->value]],
    'частичный' => [[ResultStatus::PARTIAL->value]],
])->with([false, true]);

it('обычный FAILED выдаёт выбор фабрики по первому входу без оболочки', function (bool $fallback): void {
    $factory = new RecordingFactory(fallback: $fallback);
    [$client, $transport] = poolConsumptionClient(['throwOnErrors' => true, 'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory)]);
    $transport->fake(['*' => MockResponse::make(['error' => 'bad'], 400)]);
    $executor = new DeferredExecutor($client->execution(), reverse: true);
    $client->executorOverride = $executor;
    $callbacks = 0;
    $pool = $client->pool([(new ProbeRequest())->withTraceId('first'), (new ProbeRequest())->withTraceId('second')], 2)
        ->withExceptionHandler(function () use (&$callbacks): void {
            $callbacks++;
        });
    try {
        $pool->consume();
        test()->fail('Ожидался итоговый FAILED');
    } catch (Throwable $error) {
        expect($error)->not->toBeInstanceOf(PoolConsumptionException::class);
        expect($error)->toBeInstanceOf($fallback ? $factory->results[2]->exception::class : ProviderFailure::class);
        if ($fallback) {
            expect($error)->toBe($factory->results[2]->exception);
        }
    }
    expect($callbacks)->toBe(2)->and($factory->results)->toHaveCount(3)
        ->and(array_column($factory->results, 'traceId'))->toBe(['second', 'first', 'first'])
        ->and($factory->results[2]->requestClass)->toBe(ProbeRequest::class)
        ->and($factory->results[2]->nested)->toBe([]);
})->with([false, true]);

it('PARTIAL не бросает при throwOnErrors, false обработчика не останавливает', function (): void {
    [$client, $transport] = poolConsumptionClient(['throwOnErrors' => true]);
    $transport->fake(['*' => MockResponse::sequence([MockResponse::make('bad', 400), MockResponse::success(), MockResponse::success()])]);
    $calls = 0;
    $summary = $client->pool([new ProbeRequest(), new ProbeRequest(), new ProbeRequest()], 1)
        ->withResponseHandler(function () use (&$calls): bool {
            $calls++;
            return false;
        })
        ->consume();
    expect($calls)->toBe(3)->and($summary->status)->toBe(ResultStatus::PARTIAL)
        ->and($summary->failed)->toBe(1)->and($summary->successful)->toBe(2);
});

it('без handlers все отказы возвращают сводку либо выбранную ошибку', function (bool $throw): void {
    $factory = new RecordingFactory();
    [$client, $transport] = poolConsumptionClient(['throwOnErrors' => $throw, 'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory)]);
    $transport->fake(['*' => MockResponse::make('bad', 400)]);
    $pool = $client->pool([new ProbeRequest(), new ProbeRequest()], 1);
    if ($throw) {
        expect(fn () => $pool->consume())->toThrow(ProviderFailure::class);
    } else {
        expect($pool->consume()->failed)->toBe(2);
    }
    expect($factory->results)->toHaveCount($throw ? 1 : 0);
})->with([false, true]);

it('сбой фабрики при callback или итоговой выдаче сохраняет сводку и причину', function (bool $handler): void {
    $cause = new LogicException('factory failed');
    $factory = new RecordingFactory(failure: $cause);
    [$client, $transport] = poolConsumptionClient(['throwOnErrors' => true, 'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory)]);
    $transport->fake(['*' => MockResponse::make('bad', 400)]);
    $executor = new DeferredExecutor($client->execution());
    $client->executorOverride = $executor;
    $pool = $client->pool([new ProbeRequest(), new ProbeRequest()], 2);
    if ($handler) {
        $pool = $pool->withExceptionHandler(static fn () => test()->fail('Фабрика не выдала исключение'));
    }
    try {
        $pool->consume();
        test()->fail('Ожидалась авария фабрики');
    } catch (PoolConsumptionException $error) {
        expect($error->summary->terminationReason)->toBe(PoolTerminationReason::FactoryFailed)
            ->and($error->summary->total)->toBe(2)
            ->and($error->getPrevious())->toBeInstanceOf(ExceptionFactoryException::class)
            ->and($error->getPrevious()->getPrevious())->toBe($cause);
        $localized = $error->localized(new LocalizationConfig(locale: 'ru'));
        expect($localized->summary)->toBe($error->summary)->and($localized->getPrevious())->toBe($error->getPrevious())
            ->and($localized->getMessage())->toContain('Обработка pool');
    }
    expect($executor->completed)->toBe(2)->and($factory->results)->toHaveCount(1);
})->with([false, true]);

it('авария источника завершает начатое и прекращает callbacks', function (int $concurrency): void {
    [$client, $transport] = poolConsumptionClient();
    $cause = new LogicException('source failed');
    $source = static function () use ($cause): Generator {
        yield 'a' => new ProbeRequest();
        throw $cause;
    };
    $calls = 0;
    try {
        $client->pool($source(), $concurrency)->withResponseHandler(function () use (&$calls): void {
            $calls++;
        })->consume();
        test()->fail('Ожидалась авария источника');
    } catch (PoolConsumptionException $error) {
        expect($error->getPrevious())->toBe($cause)->and($error->summary->started)->toBe(1)
            ->and($error->summary->successful)->toBe(1)->and($error->summary->firstFailedIndex)->toBeNull()
            ->and($error->summary->terminationReason)->toBe(PoolTerminationReason::SourceFailed);
    }
    expect($transport->getRecorded())->toHaveCount(1)->and($calls)->toBe($concurrency === 1 ? 1 : 0);
})->with([1, 2]);

it('ошибки методов Iterator сохраняют позицию и исходную причину', function (string $method): void {
    [$client] = poolConsumptionClient();
    $cause = new LogicException($method);
    try {
        $client->pool(new FailingIterator($method, $cause), 1)->consume();
        test()->fail('Ожидалась авария Iterator');
    } catch (PoolConsumptionException $error) {
        expect($error->getPrevious())->toBe($cause)->and($error->summary->terminationReason)->toBe(PoolTerminationReason::SourceFailed)
            ->and($error->summary->total)->toBe($method === 'rewind' ? 0 : 1);
    }
})->with(['rewind', 'valid', 'current', 'next']);

it('getIterator и count имеют source_failed, неизвестный размер не читается', function (string $method): void {
    [$client, $transport] = poolConsumptionClient();
    $cause = new LogicException($method);
    $source = new class ($method, $cause) implements IteratorAggregate, Countable {
        public int $reads = 0;
        public function __construct(private string $method, private Throwable $cause)
        {
        }
        public function getIterator(): Traversable
        {
            $this->reads++;
            if ($this->method === 'getIterator') {
                throw $this->cause;
            } yield new ProbeRequest();
        }
        public function count(): int
        {
            if ($this->method === 'count') {
                throw $this->cause;
            } return 1;
        }
    };
    try {
        $client->pool($source, $method === 'count' ? static fn (int $n): int => $n : 1)->consume();
        test()->fail('Ожидалась авария источника');
    } catch (PoolConsumptionException $error) {
        expect($error->getPrevious())->toBe($cause)->and($error->summary->total)->toBe(0)
            ->and($error->summary->terminationReason)->toBe(PoolTerminationReason::SourceFailed);
    }
    expect($source->reads)->toBe($method === 'count' ? 0 : 1)->and($transport->getRecorded())->toBeEmpty();
})->with(['getIterator', 'count']);

it('не подсчитывает источник при числе и вызывает resolver один раз при известном размере', function (): void {
    [$client] = poolConsumptionClient();
    $source = new class implements IteratorAggregate, Countable {
        public int $counts = 0;
        public function getIterator(): Traversable
        {
            yield new ProbeRequest();
            yield new ProbeRequest();
        }
        public function count(): int
        {
            $this->counts++;
            return 2;
        }
    };
    expect($client->pool($source, 1)->consume()->total)->toBe(2)->and($source->counts)->toBe(0);
    $arguments = [];
    expect($client->pool($source, function (int $pending, int $completed) use (&$arguments): int {
        $arguments[] = [$pending, $completed];
        return 2;
    })->consume()->total)->toBe(2);
    expect($arguments)->toBe([[2, 0]])->and($source->counts)->toBe(1);
});

it('непригодный resolver отклоняется до обхода и отправок', function (string $mode): void {
    [$client, $transport] = poolConsumptionClient();
    $read = false;
    $generator = static function () use (&$read): Generator {
        $read = true;
        yield new ProbeRequest();
    };
    $source = $mode === 'unknown' ? $generator() : [new ProbeRequest()];
    $resolver = match ($mode) {
        'throws' => static fn () => throw new LogicException('resolver'),
        'invalid' => static fn () => 'wrong',
        default => static fn () => 2,
    };
    expect(fn () => $client->pool($source, $resolver)->consume())->toThrow(ConfigurationException::class);
    expect($read)->toBeFalse()->and($transport->getRecorded())->toBeEmpty();
})->with(['unknown', 'throws', 'invalid']);

it('невалидный очередной элемент использует позицию и сохраняет выполненные запросы', function (): void {
    [$client, $transport] = poolConsumptionClient();
    try {
        $client->pool(['one' => new ProbeRequest(), 'evil-key' => false], 1)->consume();
        test()->fail('Ожидалась ошибка элемента');
    } catch (PoolConsumptionException $error) {
        expect($error->getPrevious())->toBeInstanceOf(ConfigurationException::class)
            ->and($error->getPrevious()->getMessage())->toContain('pool[1]')
            ->and($error->summary->total)->toBe(1)->and($error->summary->firstFailedIndex)->toBeNull();
    }
    expect($transport->getRecorded())->toHaveCount(1);
});

it('stopOnFailure не заглядывает в следующий элемент и сохраняет обработчики начатого', function (int $concurrency): void {
    [$client, $transport] = poolConsumptionClient(['pool' => new PoolConfig(stopOnFailure: true)]);
    $transport->fake(['*' => MockResponse::make('bad', 400)]);
    $issued = $callbacks = 0;
    $source = static function () use (&$issued, $concurrency): Generator {
        for ($i = 0; $i < $concurrency; $i++) {
            $issued++;
            yield new ProbeRequest();
        }
        throw new LogicException('Источник не должен продвигаться');
    };
    $summary = $client->pool($source(), $concurrency)
        ->withResponseHandler(function () use (&$callbacks): void {
            $callbacks++;
        })->consume();
    expect($summary->terminationReason)->toBe(PoolTerminationReason::StopOnFailure)
        ->and($issued)->toBe($concurrency)->and($callbacks)->toBe($concurrency)->and($summary->total)->toBe($concurrency);
})->with([1, 3]);
