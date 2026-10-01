<?php

declare(strict_types=1);

use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Stubs\Core\TestHttpClient;
use ApiSutra\Tests\Stubs\Execution\Pool\NonRecordingTransport;
use ApiSutra\Tests\Stubs\HookedClient;
use ApiSutra\Tests\Stubs\Hooks\EarlyReturnHook;
use ApiSutra\Tests\Stubs\Requests\CacheableRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\TransferProgress\GateRequest;
use ApiSutra\Tests\Stubs\TransferProgress\ProgressTransport;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Timing\ExecutionDeadline;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Transport\RecordingTransport;
use ApiSutra\VO\Http\TransferProgress;
use GuzzleHttp\Psr7\HttpFactory;
use ApiSutra\Tests\Support\SpyCache;
use Revolt\EventLoop;

it('сохраняет immutable callback, поддерживает callable и явно снимает опцию', function (): void {
    $original = RequestOptions::empty();
    $receiver = new class {
        public function receive(TransferProgress $progress): void
        {
        }
    };
    $options = $original->withTransferProgress([$receiver, 'receive']);
    $cleared = $options->withoutTransferProgress()->withRetryDelay(jitter: false)->withTimeout(2);
    expect($original->getTransferProgress())->toBeNull()
        ->and($options->withRetry(2)->getTransferProgress())->toBe($options->getTransferProgress())
        ->and($cleared->getTransferProgress())->toBeNull();
    $request = new GateRequest('/json');
    $execution = $request->withTransferProgress([$receiver, 'receive']);
    expect($request->getTransferProgress())->toBeNull()
        ->and($execution->getOptions()->getTransferProgress())->toBeInstanceOf(Closure::class)
        ->and($execution->withoutTransferProgress()->withRetry(2)->getOptions()->getTransferProgress())->toBeNull();
});

it('передаёт счётчики, изменение total, канонический trace и закрывает поздние уведомления', function (bool $async): void {
    $transport = new ProgressTransport();
    $logger = new MemoryLogger();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', logger: $logger), $transport);
    $seen = [];
    $receiver = new stdClass();
    $weak = WeakReference::create($receiver);
    $execution = (new GateRequest('/json'))->setClient($client)->withTransferProgress(
        static function (TransferProgress $p) use (&$seen, $receiver): void {
            $seen[] = $p;
        }
    );
    $result = ($async ? $execution->sendAsync()->wait() : $execution->send())->raw();
    expect($result->isSuccess())->toBeTrue()->and($seen)->toHaveCount(3)
        ->and(array_map(static fn ($p) => $p->downloadTotal, $seen))->toBe([null, 10, 10]);
    foreach ($seen as $p) {
        expect($p->trace)->toBe($result->trace)->and($p->attempt)->toBe(1);
    }
    $transport->requests[0]->transportOptions->transferProgress->__invoke(10, 11, 0, 0);
    expect($seen)->toHaveCount(3);
    $terminals = array_filter($logger->records, static fn ($r) => ($r['context']['event'] ?? '') === 'completed');
    expect($terminals)->toHaveCount(1);
    // История fake хранит исходный request с RequestOptions; проверяем отдельно получатель транспорта.
    $late = $transport->requests[0]->transportOptions->transferProgress;
    unset($execution, $receiver, $result, $transport, $client);
    $late(10, 12, 0, 0);
    EventLoop::run();
    gc_collect_cycles();
    expect($weak->get())->toBeNull();
})->with([false, true]);

it('не передаёт обработчик без opt-in и после снятия, повторный вызов явно включает его', function (): void {
    $transport = new ProgressTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $request = (new GateRequest('/json'))->setClient($client);
    $request->send();
    $request->withTransferProgress(static function (): void {
    })->withoutTransferProgress()->send();
    expect($transport->requests[0]->transportOptions->transferProgress)->toBeNull()
        ->and($transport->requests[1]->transportOptions->transferProgress)->toBeNull();
});

it('отказывает неизвестному transport или PSR adapter до auth, не меняя путь без opt-in', function (bool $psr, bool $async): void {
    RefreshingAuthenticator::reset();
    RefreshingAuthenticator::$shouldRefresh = true;
    $sender = $psr ? new TestHttpClient() : new NonRecordingTransport();
    $factory = new HttpFactory();
    $transport = $psr ? new HttpTransport($sender, $factory, $factory) : $sender;
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', auth: new RefreshingAuthenticator()), $transport);
    $request = (new GateRequest('/json'))->setClient($client)->withTransferProgress(static function (): void {
    });
    $result = ($async ? $request->sendAsync()->wait() : $request->send())->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')
        ->and(RefreshingAuthenticator::$refreshCalls)->toBe(0)
        ->and($psr ? $sender->lastRequest : $sender->sent)->toBe($psr ? null : 0);
})->with([false, true])->with([false, true]);

it('не создаёт прогресс на cache hit, EarlyReturn и просроченном бюджете', function (): void {
    $transport = new ProgressTransport();
    $cache = new SpyCache();
    $config = new ClientConfig(baseUrl: 'https://fixture.test', cacheConfig: new CacheConfig(store: $cache));
    $client = new TestClient($config, $transport);
    $calls = 0;
    $callback = static function () use (&$calls): void {
        ++$calls;
    };
    $request = (new CacheableRequest('fixture'))->setClient($client)->withTransferProgress($callback);
    expect($request->send()->raw()->isSuccess())->toBeTrue();
    $first = $calls;
    expect($first)->toBeGreaterThan(0)->and($request->send()->raw()->isSuccess())->toBeTrue()
        ->and($calls)->toBe($first)->and($transport->requests)->toHaveCount(1);
    // Даже попадание в кеш не скрывает неподдерживаемую декларацию прогресса.
    $unsupported = new NonRecordingTransport();
    $other = new TestClient($config, $unsupported);
    $failed = (new CacheableRequest('fixture'))->setClient($other)->withTransferProgress($callback)->send()->raw();
    expect($failed->errors->first()->code->value)->toBe('configuration_error')->and($unsupported->sent)->toBe(0);
    $hooks = new HookRegistry();
    $hooks->on(Hook::BeforeSend, new EarlyReturnHook());
    $early = new HookedClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport, $hooks);
    expect((new GateRequest('/json'))->setClient($early)->withTransferProgress($callback)->send()->raw()->isSuccess())
        ->toBeTrue()->and($calls)->toBe($first);
    $expired = (new GateRequest('/json'))->setClient($client)->withTransferProgress($callback)
        ->withDeadline(ExecutionDeadline::afterMs(0))->send()->raw();
    expect($expired->isFailed())->toBeTrue()->and($calls)->toBe($first)->and($transport->requests)->toHaveCount(1);
});

it('recording делегирует прогресс, а playback принимает его без событий', function (bool $async): void {
    $inner = new ProgressTransport();
    $path = tempnam(sys_get_temp_dir(), 'apisutra-progress-recording-');
    unlink($path);
    mkdir($path);
    try {
        $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new RecordingTransport($inner, $path));
        $calls = 0;
        $callback = static function () use (&$calls): void {
            ++$calls;
        };
        $request = (new GateRequest('/json'))->setClient($client)->withTransferProgress($callback);
        expect(($async ? $request->sendAsync()->wait() : $request->send())->raw()->isSuccess())->toBeTrue()
            ->and($calls)->toBe(3);
        $json = file_get_contents(glob($path . '/*.json')[0]);
        expect($json)->not->toContain('transferProgress')->not->toContain('Closure');
        $playback = new MockTransport();
        $playback->loadFixtures($path);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $playback);
        $request = (new GateRequest('/json'))->setClient($client)->withTransferProgress($callback);
        expect(($async ? $request->sendAsync()->wait() : $request->send())->raw()->isSuccess())->toBeTrue()
            ->and($calls)->toBe(3)->and($playback->getRecorded())->toHaveCount(1);
    } finally {
        foreach (glob($path . '/*') as $file) {
            unlink($file);
        }
        rmdir($path);
    }
})->with([false, true]);

it('штатный mock принимает опцию и не рисует искусственный прогресс', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $calls = 0;
    $result = (new GateRequest('/json'))->setClient($client)->withTransferProgress(
        static function () use (&$calls): void {
            ++$calls;
        }
    )->send()->raw();
    expect($result->isSuccess())->toBeTrue()->and($calls)->toBe(0);
});
