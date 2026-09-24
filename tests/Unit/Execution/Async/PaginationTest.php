<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Execution\Async\AsyncRuntime;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Tests\Stubs\Pagination\PageRequest;
use ApiSutra\Tests\Stubs\Auth\LockAwareAuthenticator;
use ApiSutra\Tests\Stubs\Core\TestHttpClient;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\LocalTimeoutServer;
use ApiSutra\Transport\HttpTransport;
use GuzzleHttp\Promise\CancellationException;
use GuzzleHttp\Psr7\HttpFactory;
use Revolt\EventLoop;

it('перекрывает реальный HTTP только при явной конкурентности', function (int $concurrency): void {
    $server = new LocalTimeoutServer();
    $client = new TestClient(new ClientConfig(baseUrl: $server->httpUrl), HttpTransport::createDefault());
    $result = new PageRequest()->setClient($client)->paginate()->withConcurrency($concurrency)->all();
    expect(array_column($result->items(), 'page'))->toBe([1, 2, 3, 4, 5, 6]);
    expect(max(array_column($result->items(), 'peak')))->toBe($concurrency);
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
})->with([1, 3]);

it('отклоняет неподдерживаемую конкурентность перед bootstrap auth и HTTP', function (bool $async): void {
    $http = new TestHttpClient();
    $factory = new HttpFactory();
    $auth = new LockAwareAuthenticator();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://sync.test', timeout: 0, connectTimeout: 0, auth: $auth), new HttpTransport($http, $factory, $factory));
    $request = new PageRequest()->setClient($client)->rules(PaginationRule::pages(2, concurrency: 2));
    $result = $async ? $request->sendAsync()->wait()->raw() : $request->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')->and($auth->shouldRefreshCalls)->toBe(0)->and($http->lastRequest)->toBeNull();
    new PageRequest()->setClient($client)->withoutAuth()->paginate()->pages(1);
    expect($http->lastRequest)->not->toBeNull();
})->with([false, true]);

it('отменяет страницы в HTTP или retry timer и освобождает задачи без отмены соседа', function (bool $retry): void {
    $server = new LocalTimeoutServer();
    $client = new TestClient(new ClientConfig(baseUrl: $server->httpUrl, retry: new RetryConfig(baseDelay: 1000, jitter: false)), HttpTransport::createDefault());
    $request = new PageRequest(ms: $retry ? 0 : 1000, status: $retry ? 503 : 200)->setClient($client)->rules(PaginationRule::all(concurrency: 3));
    $promise = $request->sendAsync();
    $runtime = new AsyncRuntime();
    $cancel = $runtime->start(function () use ($runtime, $promise): void {
        $runtime->sleep(30);
        $promise->cancel();
    });
    expect(fn () => $promise->wait())->toThrow(CancellationException::class);
    $cancel->wait();
    expect(new PageRequest()->setClient($client)->rules(PaginationRule::pages(1))->sendAsync()->wait()->raw()->isSuccess())->toBeTrue();
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
})->with([false, true]);

it('не запускает HTTP из destructor брошенного обхода и работает внутри запущенного loop', function (): void {
    $server = new LocalTimeoutServer();
    $client = new TestClient(new ClientConfig(baseUrl: $server->httpUrl), HttpTransport::createDefault());
    $request = new PageRequest(ms: 1000)->setClient($client)->rules(PaginationRule::all(concurrency: 3));
    $promise = $request->sendAsync();
    unset($promise, $request);
    gc_collect_cycles();
    expect(EventLoop::getIdentifiers())->toBe([]);
    $done = false;
    EventLoop::queue(function () use ($client, &$done): void {
        $result = new PageRequest()->setClient($client)->rules(PaginationRule::all(concurrency: 3))->sendAsync()->wait()->raw();
        $done = $result->isSuccess();
    });
    EventLoop::run();
    expect($done)->toBeTrue()->and(EventLoop::getIdentifiers())->toBe([]);
});
