<?php

declare(strict_types=1);

use ApiSutra\Contracts\Interfaces\Concurrency\ConcurrencyResolverInterface;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PoolConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Execution\PoolExecutor;
use ApiSutra\Result\PoolResult;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Tests\Stubs\Execution\DeferredExecutor;
use ApiSutra\Tests\Stubs\Execution\ExecutorClient;
use GuzzleHttp\Promise\PromiseInterface;

describe('PoolExecutor', function () {
    it('останавливается при stopOnFailure', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::serverError(),
                MockResponse::success(['id' => 1, 'name' => 'A']),
                MockResponse::success(['id' => 2, 'name' => 'B']),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            pool: new PoolConfig(stopOnFailure: true),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);

        $pool = new PoolExecutor(
            client: $client,
            requests: [
                new SimpleGetRequest('one'),
                new SimpleGetRequest('two'),
                new SimpleGetRequest('three'),
            ],
            concurrency: 1,
            config: $config->pool,
        );

        $result = $pool->send();

        expect($result->results()->countTotal())->toBe(1);
        expect($transport->getRecorded())->toHaveCount(1);
    });

    it('останавливается при stopOnFailure с concurrency > 1', function () {
        $transport = new MockTransport();
        $calls = 0;
        $transport->fake([
            SimpleGetRequest::class => function () use (&$calls) {
                $calls++;
                return $calls === 1
                    ? MockResponse::serverError()
                    : MockResponse::success(['id' => $calls, 'name' => 'ok']);
            },
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            pool: new PoolConfig(stopOnFailure: true),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);

        $pool = new PoolExecutor(
            client: $client,
            requests: [
                new SimpleGetRequest('one'),
                new SimpleGetRequest('two'),
                new SimpleGetRequest('three'),
                new SimpleGetRequest('four'),
                new SimpleGetRequest('five'),
            ],
            concurrency: 3,
            config: $config->pool,
        );

        $result = $pool->send();

        expect($result->results()->countTotal())->toBe(3);
        expect($transport->getRecorded())->toHaveCount(3);
    });

    it('возвращает partial для пустой коллекции', function () {
        $transport = new MockTransport();
        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://provider.test', environment: Environment::Testing),
            $transport,
        );

        $pool = new PoolExecutor(
            client: $client,
            requests: [],
            concurrency: 2,
        );

        $result = $pool->send();

        expect($result->results()->countTotal())->toBe(0);
        expect($result->status)->toBe(ResultStatus::PARTIAL);
        expect($transport->getRecorded())->toHaveCount(0);
    });

    it('возвращает partial для смешанных результатов', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::success(['id' => 1, 'name' => 'A']),
                MockResponse::serverError(),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://provider.test', environment: Environment::Testing),
            $transport,
        );

        $pool = new PoolExecutor(
            client: $client,
            requests: [
                new SimpleGetRequest('one'),
                new SimpleGetRequest('two'),
            ],
            concurrency: 1,
        );

        $result = $pool->send();

        expect($result->status)->toBe(ResultStatus::PARTIAL);
        expect($result->results()->countTotal())->toBe(2);
        expect($transport->getRecorded())->toHaveCount(2);
    });

    it('вызывает response handler для каждого результата', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::success(['id' => 1, 'name' => 'A']),
                MockResponse::success(['id' => 2, 'name' => 'B']),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://provider.test',
                environment: Environment::Testing,
            ),
            $transport,
        );

        $calls = 0;
        $pool = (new PoolExecutor(
            client: $client,
            requests: [
                new SimpleGetRequest('one'),
                new SimpleGetRequest('two'),
            ],
            concurrency: 1,
        ))->withResponseHandler(function () use (&$calls): void {
            $calls++;
        });

        $pool->send();

        expect($calls)->toBe(2);
    });

    it('sendAsync возвращает PoolResult', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::sequence([
                MockResponse::success(['id' => 1, 'name' => 'A']),
                MockResponse::success(['id' => 2, 'name' => 'B']),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://provider.test',
                environment: Environment::Testing,
            ),
            $transport,
        );

        $pool = new PoolExecutor(
            client: $client,
            requests: [
                new SimpleGetRequest('one'),
                new SimpleGetRequest('two'),
            ],
            concurrency: 1,
        );

        $result = $pool->sendAsync()->wait();

        expect($result)->toBeInstanceOf(PoolResult::class);
        expect($result->results()->countTotal())->toBe(2);
        expect($transport->getRecorded())->toHaveCount(2);
    });
});

it('pool использует позиции вместо произвольных ключей и сохраняет eager проверку', function (bool $async, bool $invalid): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 1, 'name' => 'ok'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pool.test'), $transport);
    $source = static function () use ($invalid): Generator {
        yield 'one' => new SimpleGetRequest('a');
        yield 42 => $invalid ? false : new SimpleGetRequest('b');
        yield 'one' => new SimpleGetRequest('c');
    };
    $run = fn () => $async ? $client->pool($source())->sendAsync()->wait() : $client->pool($source())->send();
    if ($invalid) {
        expect($run)->toThrow(ConfigurationException::class, 'pool[1]');
        expect($transport->getRecorded())->toBeEmpty();
    } else {
        expect($run()->results()->countTotal())->toBe(3)->and($transport->getRecorded())->toHaveCount(3);
    }
})->with([false, true])->with([false, true]);

it('явная конкурентность приоритетнее конфигурации и default', function (bool $direct, string $mode): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 1, 'name' => 'ok'])]);
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://pool.test', pool: $mode === 'default' ? null : new PoolConfig(concurrency: 2)), $transport);
    $executor = new DeferredExecutor($client->execution());
    $client->executorOverride = $executor;
    $requests = array_map(static fn () => new SimpleGetRequest('x'), range(1, 7));
    $concurrency = match ($mode) {
        'explicit-five' => 5, 'explicit' => 3, 'resolver' => static fn () => 4, default => null
    };
    $pool = $direct ? new PoolExecutor($client, $requests, $concurrency) : $client->pool($requests, $concurrency);
    $firstWindow = null;
    $result = $pool->withResponseHandler(function () use (&$firstWindow, $executor): void {
        $firstWindow ??= $executor->issued;
    })->sendAsync()->wait();
    expect($result->results()->countTotal())->toBe(7)
        ->and($firstWindow)->toBe(match ($mode) {
        'default', 'explicit-five' => 5, 'explicit' => 3, 'resolver' => 4, default => 2
        });
})->with([false, true])->with(['default', 'config', 'explicit-five', 'explicit', 'resolver']);

it('явный PoolConfig прямого executor управляет и лимитом и остановкой', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('bad', 400)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pool.test', pool: new PoolConfig(concurrency: 5)), $transport);
    $pool = new PoolExecutor($client, [new SimpleGetRequest('a'), new SimpleGetRequest('b')], config: new PoolConfig(concurrency: 1, stopOnFailure: true));
    expect($pool->send()->results()->countTotal())->toBe(1)->and($transport->getRecorded())->toHaveCount(1);
});

it('локальная остановка перекрывает конфиг, сохраняя исходный builder и другие пулы', function (
    string $method,
    bool $default,
): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('bad', 400)]);
    $config = new PoolConfig(stopOnFailure: $default);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pool.test', pool: $config), $transport);
    $requests = [new SimpleGetRequest('a'), new SimpleGetRequest('b'), new SimpleGetRequest('c')];
    $pool = $client->pool($requests, 1);
    $overridden = $default ? $pool->withStopOnFailure(false) : $pool->withStopOnFailure();
    $run = static function (PoolExecutor $pool) use ($method): int {
        $result = $pool->$method();
        if ($result instanceof PromiseInterface) {
            $result = $result->wait();
        }
        return $result instanceof PoolResult ? $result->results()->countTotal() : $result->total;
    };

    expect($overridden)->not->toBe($pool)
        ->and($run($overridden))->toBe($default ? 3 : 1)
        ->and($run($pool))->toBe($default ? 1 : 3)
        ->and($run($client->pool($requests, 1)))->toBe($default ? 1 : 3)
        ->and($client->getConfig()->pool)->toBe($config)
        ->and($config->stopOnFailure)->toBe($default);
})->with(['send', 'sendAsync', 'consume', 'consumeAsync'])->with([false, true]);

it('локальная остановка завершает активное окно и сохраняется при добавлении handler', function (string $method): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('bad', 400)]);
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://pool.test'), $transport);
    $executor = new DeferredExecutor($client->execution());
    $client->executorOverride = $executor;
    $read = $delivered = 0;
    $requests = (static function () use (&$read): Generator {
        for ($i = 0; $i < 4; $i++) {
            $read++;
            yield new SimpleGetRequest((string) $i);
        }
    })();
    $pool = $client->pool($requests, 2)->withStopOnFailure()
        ->withResponseHandler(function () use (&$delivered): void {
            $delivered++;
        });
    $result = $pool->$method();
    if ($result instanceof PromiseInterface) {
        $result = $result->wait();
    }

    expect($result instanceof PoolResult ? $result->results()->countTotal() : $result->total)->toBe(2)
        ->and($executor->issued)->toBe(2)
        ->and($executor->completed)->toBe(2)
        ->and($delivered)->toBe(2)
        ->and($read)->toBe(str_starts_with($method, 'consume') ? 2 : 4)
        ->and($transport->getRecorded())->toHaveCount(2);
})->with(['send', 'sendAsync', 'consume', 'consumeAsync']);

it('копирующая конкурентность принимает число callable и resolver без изменения исходного pool', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('bad', 400)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pool.test', pool: new PoolConfig(concurrency: 5)), $transport);
    $pool = $client->pool([new SimpleGetRequest('a'), new SimpleGetRequest('b')], 2)->withStopOnFailure();
    $calls = 0;
    $copy = $pool->withConcurrency(function (int $pending, int $completed) use (&$calls): int {
        $calls++;
        expect([$pending, $completed])->toBe([2, 0]);
        return 1;
    });
    expect($copy->send()->results()->countTotal())->toBe(1)->and($calls)->toBe(1);
    expect($pool->send()->results()->countTotal())->toBe(2);
    $resolver = new class implements ConcurrencyResolverInterface {
        public function getConcurrency(int $pending, int $completed): int { return 1; }
    };
    expect($pool->withConcurrency($resolver)->send()->results()->countTotal())->toBe(1);
    expect($copy->withConcurrency(2)->send()->results()->countTotal())->toBe(2)->and($calls)->toBe(1);
});

it('одинаково отклоняет неверный результат resolver до HTTP во всех режимах pool', function (string $method, mixed $value): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://pool.test'), $transport);
    $pool = $client->pool([new SimpleGetRequest('a')])->withConcurrency(static fn () => $value);
    expect(function () use ($pool, $method): void {
        $result = $pool->$method();
        if ($result instanceof PromiseInterface) {
            $result->wait();
        }
    })->toThrow(ConfigurationException::class);
    expect($transport->getRecorded())->toBeEmpty();
})->with(['send', 'sendAsync', 'consume', 'consumeAsync'])->with(['2', 2.9, true, null]);
