<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Contracts\Interfaces\Pagination\PaginationItemsCollectionFactoryInterface;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Pagination\PaginationItemsReader;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\DtoMapping\FactoryDto;
use ApiSutra\Tests\Stubs\Pagination\PageRequest;
use ApiSutra\Tests\Stubs\ResultContract\ProviderFailure;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;
use ApiSutra\Transport\MockTransport;

it('лениво выдаёт все значения с позиционными ключами и повторяет обход по запросу', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => function (PageRequest $request): MockResponse {
        $page = $request->getContext()->paginationOptions->getPage();
        return MockResponse::success(['data' => ['same' => $page, 'another' => $page], 'meta' => ['page' => $page, 'has_more' => $page < 2]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://items.test'), $transport);
    $paginator = new PageRequest()->setClient($client)->paginate();
    $items = $paginator->items();
    expect($transport->getRecorded())->toBe([]);
    expect(iterator_to_array($items))->toBe([1, 1, 2, 2])->and($transport->getRecorded())->toHaveCount(2);
    expect(iterator_to_array($paginator->items()))->toBe([1, 1, 2, 2])->and($transport->getRecorded())->toHaveCount(4);
});

it('выдаёт ошибку второй страницы фабрикой ровно один раз независимо от throwOnErrors', function (bool $throw): void {
    $factory = new RecordingFactory();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::sequence([
        MockResponse::success(['data' => [7, 8], 'meta' => ['page' => 1, 'has_more' => true]]),
        MockResponse::make(['error' => 'fixture'], status: 400),
    ])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://items.test', throwOnErrors: $throw, resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory)), $transport);
    $seen = [];
    try {
        foreach (new PageRequest()->setClient($client)->paginate()->items() as $item) {
            $seen[] = $item;
        }
        $this->fail('Ожидалась ошибка второй страницы');
    } catch (ProviderFailure) {
        expect($seen)->toBe([7, 8])->and($factory->results)->toHaveCount(1);
    }
    expect($transport->getRecorded())->toHaveCount(2);
})->with([false, true]);

it('отклоняет конкурентный iterator до HTTP', function (): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://items.test'), $transport);
    $items = new PageRequest()->setClient($client)->paginate()->withConcurrency(2)->items();
    expect(fn () => iterator_to_array($items))->toThrow(ConfigurationException::class);
    expect($transport->getRecorded())->toBe([]);
});

it('не сериализует исходные DTO и отвергает toArray без iterable', function (): void {
    $dto = FactoryDto::create(7);
    $collection = new class([$dto]) extends ArrayIterator {
        public function toArray(): array
        {
            throw new LogicException('Не вызывать сериализацию для обхода');
        }
    };
    $reader = new PaginationItemsReader();
    expect($reader->toArray($collection))->toBe([$dto])->and($dto->toArray())->toBe(['record_id' => 7]);
    $unsupported = new class {
        public function toArray(): array
        {
            return [1];
        }
    };
    expect(fn () => $reader->read($unsupported))->toThrow(ConfigurationException::class);
    $duplicates = (function (): Generator {
        yield 'same' => null;
        yield 'same' => 2;
    })();
    expect(fn () => $reader->toArray($duplicates))->toThrow(HydrationException::class);
});

it('отказывает при коллизии строковых ключей без потери исходных страниц при любой fail strategy', function (FailStrategy $strategy): void {
    $transport = new MockTransport();
    $transport->fake(['*' => function (PageRequest $request): MockResponse {
        $page = $request->getContext()->paginationOptions->getPage();
        return MockResponse::success(['data' => ['synthetic-secret-key' => $page], 'meta' => ['page' => $page, 'has_more' => $page < 2]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://items.test'), $transport);
    $result = new PageRequest()->setClient($client)->paginate()->withFailStrategy($strategy)->all();
    expect($result->isFailed())->toBeTrue()->and($result->data)->toBeNull()
        ->and($result->errors->first()->code)->toBe(ErrorCode::HydrationError)
        ->and($result->errors->first()->context['reason'])->toBe('pagination_item_key_collision')
        ->and($result->pages()->all())->toHaveCount(2)
        ->and($result->errors->first()->message)->not->toContain('synthetic-secret-key');
})->with(FailStrategy::cases());

it('сохраняет числовые элементы и уникальные строковые ключи в агрегате', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => function (PageRequest $request): MockResponse {
        $page = $request->getContext()->paginationOptions->getPage();
        return MockResponse::success(['data' => [5 => $page, 'key' . $page => $page], 'meta' => ['page' => $page, 'has_more' => $page < 2]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://items.test'), $transport);
    expect(new PageRequest()->setClient($client)->paginate()->all()->items())->toBe([1, 'key1' => 1, 2, 'key2' => 2]);
});

it('сохраняет trace и закрывает удержанный generator только после освобождения', function (): void {
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['data' => [1, 2], 'meta' => ['page' => 1, 'has_more' => true]])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://items.test', logger: $logger, logLevel: 'debug'), $transport);
    $held = new PageRequest()->setClient($client)->withTraceId('items-trace')->paginate()->items();
    foreach ($held as $item) {
        break;
    }
    $events = fn () => array_column(array_column($logger->records, 'context'), 'event');
    expect($events())->not->toContain('abandoned');
    unset($held);
    expect(array_count_values($events())['abandoned'])->toBe(1)->and($transport->getRecorded())->toHaveCount(1);
    $contexts = array_column($logger->records, 'context');
    foreach ($contexts as $context) {
        if (isset($context['event'])) {
            expect($context['trace'])->toBe('items-trace');
        }
    }
});

it('различает отказ iterable и ошибку приложения при завершении trace', function (bool $readerFails): void {
    $failure = new LogicException('Сбой iterable');
    $factory = new class ($readerFails, $failure) implements PaginationItemsCollectionFactoryInterface {
        public function __construct(private bool $fails, private Throwable $failure)
        {
        }

        public function make(array $items): object
        {
            return (function () use ($items): Generator {
                yield $items[0];
                if ($this->fails) {
                    throw $this->failure;
                }
                yield $items[1];
            })();
        }
    };
    $logger = new MemoryLogger();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['data' => [1, 2], 'meta' => ['page' => 1, 'has_more' => true]])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://items.test', hydration: new HydrationConfig(),
        paginationConfig: new PaginationConfig(itemsCollectionFactory: $factory),
        logger: $logger, logLevel: 'debug',
    ), $transport);
    $caught = null;
    try {
        foreach (new PageRequest()->setClient($client)->paginate()->items() as $item) {
            if (!$readerFails) {
                throw $failure;
            }
        }
    } catch (Throwable $exception) {
        $caught = $exception;
    }
    expect($caught)->toBeInstanceOf(Throwable::class);
    if (!$readerFails) {
        expect($caught)->toBe($failure);
    }
    $events = array_column(array_column($logger->records, 'context'), 'event');
    expect(array_count_values($events)[$readerFails ? 'failed' : 'abandoned'])->toBe(1)
        ->and($events)->not->toContain($readerFails ? 'abandoned' : 'failed')
        ->and($transport->getRecorded())->toHaveCount(1);
})->with([true, false]);
