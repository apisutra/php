<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PoolConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Execution\Async\AsyncRuntime;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Execution\PoolExecutor;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Async\ProbeRequest;
use ApiSutra\Tests\Stubs\Auth\LockAwareAuthenticator;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\Tests\Stubs\Core\TestHttpClient;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\LocalTimeoutServer;
use ApiSutra\Timing\ExecutionDeadline;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\CancellationException;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\HttpFactory;
use Revolt\EventLoop;
use ApiSutra\Exceptions\ControlFlow\ExecutionCancelledException;
use ApiSutra\Tests\Support\LocalFileServer;
use ApiSutra\Tests\Stubs\Files\ControlledStream;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use ApiSutra\Tests\Stubs\Requests\BinaryUploadRequest;
use ApiSutra\VO\Files\FileInput;

it('runs client, request and execution async together with real HTTP and one result per handle', function (): void {
    $server = new LocalTimeoutServer();
    $client = new TestClient(new ClientConfig(baseUrl: $server->httpUrl), HttpTransport::createDefault());
    $before = EventLoop::getIdentifiers();
    $a = $client->sendAsync(new ProbeRequest(100));
    $b = (new ProbeRequest(100))->setClient($client)->sendAsync();
    $c = (new ProbeRequest(100))->setClient($client)->withTraceId('c')->sendAsync();
    expect($a->getState())->toBe('pending');
    $results = Utils::all([$a, $b, $c])->wait();
    expect(max(array_map(static fn ($result) => $result->raw()->data['peak'], $results)))->toBe(3);
    expect($a->wait())->toBe($results[0])->and($a->wait()->dataOrFail())->toBe($results[0]->dataOrFail());
    $promises = [(new ProbeRequest())->setClient($client)->resolvedAsync(), (new ProbeRequest())->setClient($client)->withTraceId('resolved')->resolvedAsync()];
    expect(Utils::all($promises)->wait())->toHaveCount(2);
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe($before);
});

it('isolates the same request contexts across waits and restores nested calls', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://async.test'), $transport);
    $seen = [];
    $request = new ProbeRequest(before: function (PipelineContext $context, PipelineContext $protected) use (&$seen, $client): void {
        expect($protected)->toBe($context)->and($context->request->getContext())->toBe($context);
        $seen[] = $context->traceId;
        $client->send((new ProbeRequest())->withTraceId('child'))->raw();
        AsyncTask::current()->runtime->sleep($context->traceId === 'a' ? 10 : 1);
        expect($context->request->getContext())->toBe($context);
    }, after: function (PipelineContext $context, PipelineContext $protected): void {
        expect($protected)->toBe($context)->and($context->request->getContext())->toBe($context);
    });
    $request->setClient($client);
    $a = $request->withTraceId('a')->sendAsync();
    $b = $request->withTraceId('b')->sendAsync();
    expect($b->wait()->raw()->traceId)->toBe('b')->and($a->wait()->raw()->traceId)->toBe('a');
    expect($seen)->toBe(['a', 'b'])->and($request->getContext()->traceId)->toBe('a');
});

it('lets another client finish during delay, backoff, Retry-After or quota wait', function (string $kind): void {
    $transport = new MockTransport();
    $attempts = 0;
    $transport->fake(['*' => function () use (&$attempts, $kind): MockResponse {
        $attempts++;
        return $attempts === 1 && in_array($kind, ['backoff', 'retry-after'], true)
            ? MockResponse::make('busy', 503, $kind === 'retry-after' ? ['Retry-After' => '1'] : [])
            : MockResponse::success(['ok' => true]);
    }]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://waiting.test',
        rateLimit: $kind === 'quota' ? new RateLimitConfig(1, 1) : null,
        retry: new RetryConfig(attempts: 2, baseDelay: 30, jitter: false),
    ), $transport);
    if ($kind === 'quota') {
        $client->send(new ProbeRequest())->raw();
    }
    $a = $client->sendAsync((new ProbeRequest())->withDelay($kind === 'delay' ? 30 : 0));
    $other = new MockTransport();
    $other->fake(['*' => MockResponse::success()]);
    $otherClient = new TestClient(new ClientConfig(baseUrl: 'https://other.test'), $other);
    expect($otherClient->sendAsync(new ProbeRequest())->wait()->raw()->isSuccess())->toBeTrue();
    expect($a->getState())->toBe('pending');
    expect($a->wait()->raw()->isSuccess())->toBeTrue();
})->with(['delay', 'backoff', 'retry-after', 'quota']);

it('honors external deadlines during real HTTP and before a delayed scheduler starts HTTP', function (): void {
    $server = new LocalTimeoutServer();
    $client = new TestClient(new ClientConfig(baseUrl: $server->httpUrl), HttpTransport::createDefault());
    $deadline = ExecutionDeadline::afterMs(180);
    for ($i = 0; $i < 3; $i++) {
        $result = $client->sendAsync((new ProbeRequest(70))->withDeadline($deadline))->wait()->raw();
        expect($result->isSuccess())->toBe($i < 2);
    }
    expect($result->errors->first()->context['reason'])->toBe('execution_deadline_exceeded');
    $late = $client->sendAsync((new ProbeRequest())->withDeadline(ExecutionDeadline::afterMs(10)));
    usleep(20000);
    expect($late->wait()->raw()->errors->first()->context['reason'])->toBe('execution_deadline_exceeded');
});

it('rejects unsupported PSR async before auth while retaining synchronous send and pool one', function (): void {
    $http = new TestHttpClient();
    $factory = new HttpFactory();
    $auth = new LockAwareAuthenticator();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://sync.test', timeout: 0, connectTimeout: 0, auth: $auth), new HttpTransport($http, $factory, $factory));
    $result = $client->sendAsync(new ProbeRequest())->wait()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')
        ->and($auth->shouldRefreshCalls)->toBe(0)->and($http->lastRequest)->toBeNull();
    $request = (new ProbeRequest())->withoutAuth();
    $client->send($request)->raw();
    expect($http->lastRequest)->not->toBeNull();
    expect((new PoolExecutor($client, [$request], 1))->send()->nested[0]->errors->first()?->code->value)->not->toBe('configuration_error');
    expect((new PoolExecutor($client, [$request], 1))->sendAsync()->wait()->nested[0]->errors->first()->code->value)->toBe('configuration_error');
});

it('limits pool concurrency, calls handlers by readiness and drains on handler error', function (bool $throw): void {
    $server = new LocalTimeoutServer();
    $client = new TestClient(new ClientConfig(baseUrl: $server->httpUrl), HttpTransport::createDefault());
    $order = [];
    $pool = (new PoolExecutor($client, [new ProbeRequest(90), new ProbeRequest(10), new ProbeRequest(50)], 2))
        ->withResponseHandler(function ($result) use (&$order, $throw, $client): void {
            $order[] = $result->data['delay'];
            expect($client->send(new ProbeRequest())->raw()->isSuccess())->toBeTrue();
            if ($throw) {
                throw new RuntimeException('callback failed');
            }
        });
    if ($throw) {
        expect(fn () => $pool->send())->toThrow(RuntimeException::class, 'callback failed');
        expect($order)->toBe([10]);
    } else {
        $result = $pool->sendAsync()->wait();
        expect($order)->toBe([10, 50, 90]);
        expect(array_map(static fn ($item) => $item->data['delay'], $result->nested))->toBe([90, 10, 50]);
    }
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
})->with([false, true]);

it('cancels HTTP or retry delay without cancelling another client and cleans up context', function (bool $http): void {
    $server = new LocalTimeoutServer();
    $client = new TestClient(new ClientConfig(baseUrl: $server->httpUrl, retry: new RetryConfig(baseDelay: 1000)), HttpTransport::createDefault());
    $request = new ProbeRequest($http ? 1000 : 0, $http ? 200 : 503);
    $handle = $client->sendAsync($request);
    $runtime = new AsyncRuntime();
    $cancel = $runtime->start(function () use ($runtime, $handle): void {
        $runtime->sleep(20);
        $handle->cancel();
    });
    expect(fn () => $handle->wait()->raw())->toThrow(CancellationException::class);
    $cancel->wait();
    expect($client->sendAsync(new ProbeRequest())->wait()->raw()->isSuccess())->toBeTrue();
    expect($request->getContext()->failureException)->toBeInstanceOf(ExecutionCancelledException::class);
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
})->with([false, true]);

it('coordinates auth refresh across tasks and releases its lease on cancellation', function (bool $cancel): void {
    $auth = new LockAwareAuthenticator();
    $transport = new MockTransport();
    $refreshes = 0;
    $transport->fake([
        RefreshTokenRequest::class => function () use (&$refreshes): MockResponse {
            $refreshes++;
            AsyncTask::current()->runtime->sleep(70);
            return MockResponse::success(['token' => 'fresh']);
        },
        '*' => MockResponse::success(['ok' => true]),
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://auth.test', auth: $auth), $transport);
    $a = $client->sendAsync(new ProbeRequest());
    $b = $client->sendAsync(new ProbeRequest());
    if ($cancel) {
        $a->cancel();
        expect(fn () => $a->wait()->raw())->toThrow(CancellationException::class);
    } else {
        expect($a->wait()->raw()->isSuccess())->toBeTrue();
    }
    expect($b->wait()->raw()->isSuccess())->toBeTrue()->and($refreshes)->toBe($cancel ? 2 : 1);
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
})->with([false, true]);

it('does not retain discarded live HTTP handles or spin a ticker at shutdown', function (): void {
    EventLoop::run();
    $server = new LocalTimeoutServer();
    for ($i = 0; $i < 100; $i++) {
        $client = new TestClient(new ClientConfig(baseUrl: $server->httpUrl), HttpTransport::createDefault());
        $request = new ProbeRequest(1000);
        $weak = WeakReference::create($request);
        $handle = $client->sendAsync($request);
        unset($handle, $request, $client);
        gc_collect_cycles();
        expect($weak->get())->toBeNull()->and(EventLoop::getIdentifiers())->toBe([]);
    }
    expect(Utils::queue()->isEmpty())->toBeTrue();
    EventLoop::run();
});

it('isolates a native upload read failure from a concurrent download', function (): void {
    $server = new LocalFileServer();
    $client = new TestClient(new ClientConfig(baseUrl: $server->url), HttpTransport::createDefault());
    $stream = new ControlledStream(Psr7Utils::streamFor(str_repeat('a', 1100000)), readFails: true);
    $download = $client->sendAsync((new ProviderBDownloadRequest('one'))->withUrl($server->url . '/download?size=1100000'));
    $upload = $client->sendAsync((new BinaryUploadRequest(FileInput::fromStream($stream, 'file.bin')))->withHeader('Expect', '')->withUrl($server->url . '/upload'));
    expect($upload->wait()->raw()->errors->first()->code->value)->toBe('file_transfer_error')
        ->and($stream->reads)->toBe(1)->and($stream->closed)->toBeFalse();
    expect($download->wait()->raw()->isSuccess())->toBeTrue()->and($download->wait()->dataOrFail()->size())->toBe(1100000);
    $download->wait()->dataOrFail()->close();
});
