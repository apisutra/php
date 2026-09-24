<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Result\BatchResult;
use ApiSutra\Result\PoolResult;
use ApiSutra\Result\PoolSummary;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Async\ProbeRequest;
use ApiSutra\Tests\Stubs\Execution\DeferredExecutor;
use ApiSutra\Tests\Stubs\Execution\ExecutorClient;
use ApiSutra\Tests\Stubs\Execution\RecordingExecutor;
use ApiSutra\Tests\Stubs\Request\TestResolvedResult;
use ApiSutra\Tests\Stubs\Request\TestResolvedResultFactory;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Timing\ExecutionDeadline;
use ApiSutra\Transport\MockTransport;
use GuzzleHttp\Promise\CancellationException;
use GuzzleHttp\Promise\Utils;
use Revolt\EventLoop;

it('ожидает одиночные и групповые результаты вместе и сохраняет пользовательскую фабрику', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://async.test', resolvedResultFactory: new TestResolvedResultFactory(),
    ), $transport);
    $single = $client->sendAsync((new ProbeRequest())->withDelay(5));
    $values = Utils::all([
        $single,
        $client->pool([new ProbeRequest()])->sendAsync(),
        $client->pool([new ProbeRequest()])->consumeAsync(),
        $client->batch([new ProbeRequest()])->sendAsync(),
    ])->wait();
    expect($single)->toBeInstanceOf(ResultPromiseInterface::class)
        ->and($values[0])->toBeInstanceOf(ResultHandle::class)->toBe($single->wait())
        ->and($values[0]->dataOrFail())->toBe(['ok' => true])
        ->and($values[0]->resolved())->toBeInstanceOf(TestResolvedResult::class)
        ->and($values[1])->toBeInstanceOf(PoolResult::class)
        ->and($values[2])->toBeInstanceOf(PoolSummary::class)
        ->and($values[3])->toBeInstanceOf(BatchResult::class)
        ->and($transport->getRecorded())->toHaveCount(4);
    expect((new ProbeRequest())->setClient($client)->resolvedAsync()->wait())->toBeInstanceOf(TestResolvedResult::class);
    expect((new ProbeRequest())->setClient($client)->withTraceId('wrapped')->resolvedAsync()->wait())->toBeInstanceOf(TestResolvedResult::class);
});

it('переносит ошибку запуска executor в reject без HTTP', function (bool $requestFirst): void {
    $transport = new MockTransport();
    $client = new ExecutorClient(new ClientConfig(baseUrl: 'https://async.test'), $transport);
    $executor = new RecordingExecutor($client->execution());
    $executor->failure = $failure = new LogicException('start failed');
    $client->executorOverride = $executor;
    $request = (new ProbeRequest())->setClient($client);
    $promise = $requestFirst ? $request->sendAsync() : $client->sendAsync($request);
    expect($promise)->toBeInstanceOf(ResultPromiseInterface::class);
    expect(fn () => $promise->wait())->toThrow($failure);
    expect($transport->getRecorded())->toBeEmpty();
})->with([false, true]);

it('проверяет значение стороннего Promise и локализует отказ публичной доставки', function (string $locale): void {
    $transport = new MockTransport();
    $client = new ExecutorClient(new ClientConfig(
        baseUrl: 'https://async.test', localization: new LocalizationConfig(locale: $locale),
    ), $transport);
    $executor = new DeferredExecutor($client->execution());
    $executor->invalidResult = true;
    $client->executorOverride = $executor;
    expect(fn () => $client->sendAsync(new ProbeRequest())->wait())->toThrow(
        ConfigurationException::class,
        $locale === 'ru' ? 'промис' : 'promise',
    );
    expect($transport->getRecorded())->toBeEmpty();
})->with(['en', 'ru']);

it('контекстный async наследует trace role и оставшийся deadline', function (): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://async.test'), $transport, $clock, $clock);
    $root = new ProbeRequest();
    $client->send($root->withDeadline(ExecutionDeadline::afterMs(100, $clock)))->raw();
    $parent = $root->getContext();
    $clock->advance(40);
    $child = new ProbeRequest();
    $result = $client->sendInContextAsync($child, $parent, RequestRole::Nested)->wait()->raw();
    expect($result->trace->parentExecutionId)->toBe($parent->trace->executionId)
        ->and($child->getContext()->role)->toBe(RequestRole::Nested)
        ->and($child->getContext()->budget->remainingMs())->toBe(60);
});

it('разворачивает типизированные цепочки, восстанавливает отказ и отменяет через производный Promise', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://async.test'), $transport);
    $count = $client->sendAsync(new ProbeRequest())
        ->then(fn (ResultHandle $handle) => $client->pool([new ProbeRequest()])->consumeAsync())
        ->then(static fn (PoolSummary $summary): int => $summary->total)->wait();
    expect($count)->toBe(1);
    $recovered = $client->sendAsync(new ProbeRequest())
        ->then(static fn (ResultHandle $handle) => throw new LogicException('recover'))
        ->otherwise(fn (mixed $reason) => $client->pool([new ProbeRequest()])->consumeAsync());
    expect($recovered->wait())->toBeInstanceOf(PoolSummary::class);
    $original = $client->sendAsync((new ProbeRequest())->withDelay(60000));
    $derived = $original->then(static fn (ResultHandle $handle) => $handle->raw());
    $derived->cancel();
    expect($derived->wait(false))->toBeNull();
    expect(fn () => $original->wait())->toThrow(CancellationException::class);
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBeEmpty();
});
