<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support\TransferProgress;

use ApiSutra\VO\Http\TransferProgress;
use ApiSutra\Tests\Stubs\TransferProgress\GateRequest;
use ApiSutra\Tests\Stubs\TransferProgress\GateDownloadRequest;
use ApiSutra\Tests\Stubs\TransferProgress\GatePaginatedRequest;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Continuation\ContinuationAwaitOptions;
use ApiSutra\Execution\ExecutionLocal;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Stubs\Requests\BinaryUploadRequest;
use ApiSutra\Tests\Stubs\Requests\ContinuationStartRequest;
use ApiSutra\Tests\Stubs\Requests\MultipartUploadRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;
use ApiSutra\Tests\Stubs\Tracing\TokenExtractor;
use ApiSutra\Tests\Support\LocalFileServer;
use ApiSutra\Tests\Support\LocalUrlServer;
use ApiSutra\VO\Files\FileInput;
use Composer\InstalledVersions;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\PumpStream;
use GuzzleHttp\Psr7\Utils;
use Revolt\EventLoop;
use RuntimeException;
use WeakReference;
use stdClass;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once __DIR__ . '/common.php';

report('environment', ['php' => PHP_VERSION, 'curl' => curl_version()['version'],
    'guzzle' => InstalledVersions::getPrettyVersion('guzzlehttp/guzzle'), 'os' => PHP_OS_FAMILY]);

foreach ([false, true] as $async) {
    $mode = $async ? 'async' : 'sync';
    foreach ([false, true] as $isolated) {
        $server = new LocalFileServer();
        $client = new TestClient(new ClientConfig(baseUrl: $server->url), transport());
        foreach ([['/download', 0], ['/download', 180000], ['/unknown', 180000], ['/gzip', 180000]] as [$path, $size]) {
            $last = null;
            $count = 0;
            $sawUnknown = false;
            $callback = static function (TransferProgress $p) use (&$last, &$count, &$sawUnknown): void {
                $last = $p;
                ++$count;
                $sawUnknown = $sawUnknown || $p->downloadTotal === null;
            };
            $target = $path . '?size=' . $size;
            $request = observe((new GateDownloadRequest($target))->setClient($client), $callback);
            if ($isolated) {
                $request = $request->withUrl($server->url . $target . '&sig=fixture&');
            }
            $result = ($async ? $request->sendAsync()->wait() : $request->send())->raw();
            check($result->isSuccess(), "$mode download $path");
            check($result->data->size() === $size && $last instanceof TransferProgress && $count > 0, 'данные и снимок');
            check($last->trace === $result->trace && $last->attempt === 1, 'канонический trace/attempt');
            check($sawUnknown, 'начальный неизвестный total');
            if ($path === '/unknown' || $size === 0) {
                check($last->downloadTotal === null, 'нулевой total → null');
            } elseif ($path !== '/gzip') {
                check($last->downloadTotal === $size && $last->downloaded === $size, 'известный total');
            } else {
                check($last->downloaded < $size, 'gzip счётчик отражает сжатую передачу');
            }
            report("$mode/download", ['isolated' => $isolated, 'path' => $path, 'bytes' => $size,
                'events' => $count, 'downloaded' => $last->downloaded, 'total' => $last->downloadTotal]);
            $result->data->close();
        }
        foreach (['known', 'chunked', 'multipart'] as $kind) {
            $size = 3000000;
            $remaining = $size;
            $stream = $kind === 'chunked' ? new PumpStream(static function (int $length) use (&$remaining): string|false {
                if ($remaining === 0) {
                    return false;
                }
                $take = min($length, $remaining);
                $remaining -= $take;
                return str_repeat('Z', $take);
            }) : Utils::streamFor(str_repeat('Z', $size));
            $input = FileInput::fromStream($stream, 'fixture.bin');
            $original = $kind === 'multipart' ? new MultipartUploadRequest([$input], 'fixture') : new BinaryUploadRequest($input);
            $last = null;
            $count = 0;
            $request = observe($original->setClient($client), static function (TransferProgress $p) use (&$last, &$count): void {
                $last = $p;
                ++$count;
            });
            if ($isolated) {
                $request = $request->withUrl($server->url . '/upload?sig=fixture&');
            }
            $result = ($async ? $request->sendAsync()->wait() : $request->send())->raw();
            check($result->isSuccess(), "$mode upload $kind");
            check($last instanceof TransferProgress && $last->trace === $result->trace, 'атрибуция upload');
            check($stream->isReadable(), 'пользовательский поток открыт');
            check($result->data['bytes'] === ($kind === 'multipart' ? $last->uploaded : $size), 'размер upload на сервере');
            if ($kind === 'known') {
                check($last->uploaded === $size, 'known upload bytes');
            }
            if ($kind === 'chunked') {
                check($last->uploadTotal === null && $last->uploaded > $size, 'chunked framing и неизвестный total');
            }
            if ($kind === 'multipart') {
                check($last->uploaded > $size, 'multipart framing');
            }
            report("$mode/upload", ['isolated' => $isolated, 'kind' => $kind, 'events' => $count,
                'uploaded' => $last->uploaded, 'total' => $last->uploadTotal]);
            $stream->close();
        }
        $server->close();
    }

    // Изоляция не переносит секретные defaults; опция отправки замещает native callback.
    $server = new LocalUrlServer();
    $nativeCalls = 0;
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), transport([
        'auth' => ['fixture', 'secret'], 'headers' => ['X-Provider-Secret' => 'fixture'],
        'query' => ['secret' => 'fixture'], 'body' => 'wrong',
        'cookies' => CookieJar::fromArray(['secret' => 'fixture'], '127.0.0.1'),
        'progress' => static function () use (&$nativeCalls): void {
            ++$nativeCalls;
        },
    ]));
    $count = 0;
    $request = observe((new GateRequest('/unused'))->setClient($client), static function () use (&$count): void {
        ++$count;
    })
        ->withUrl($server->url . '/a/../file?x=+&x=%20&&sig=fixture&');
    $data = ($async ? $request->sendAsync()->wait() : $request->send())->dataOrFail();
    check($data['target'] === '/a/../file?x=+&x=%20&&sig=fixture&' && $data['body'] === '', 'точный target и отсутствие body defaults');
    foreach (['authorization', 'cookie', 'x-provider-secret'] as $header) {
        check(!isset($data['headers'][$header]), 'секретный default не перенесён');
    }
    check($count > 0 && $nativeCalls === 0, 'SDK progress работает на isolated');
    $redirect = $request->withUrl($server->url . '/redirect/302');
    check(($async ? $redirect->sendAsync()->wait() : $redirect->send())->raw()->response->status === 302, 'redirect не выполнен');
    report("$mode/isolation", ['events' => $count, 'nativeEvents' => $nativeCalls]);
    $server->close();

    // Без SDK opt-in уже настроенный native callback не удаляется.
    $server = new LocalFileServer();
    $nativeCalls = 0;
    $sdkCalls = 0;
    $client = new TestClient(new ClientConfig(baseUrl: $server->url), transport([
        'progress' => static function () use (&$nativeCalls): void {
            ++$nativeCalls;
        },
    ]));
    $plain = (new GateRequest('/json'))->setClient($client);
    check(($async ? $plain->sendAsync()->wait() : $plain->send())->raw()->isSuccess(), 'native запрос успешен');
    check($nativeCalls > 0, 'native progress сохранён без opt-in');
    $nativeCalls = 0;
    $observed = $plain->withTransferProgress(static function () use (&$sdkCalls): void {
        ++$sdkCalls;
    });
    check(($async ? $observed->sendAsync()->wait() : $observed->send())->raw()->isSuccess(), 'SDK запрос успешен');
    check($sdkCalls > 0 && $nativeCalls === 0, 'SDK опция перекрывает native на этой отправке');
    $server->close();

    // Raw curl запрещён только при opt-in; ранний guard предшествует авторизации.
    $server = new LocalFileServer();
    RefreshingAuthenticator::reset();
    RefreshingAuthenticator::$shouldRefresh = true;
    $client = new TestClient(
        new ClientConfig(baseUrl: $server->url, auth: new RefreshingAuthenticator()),
        transport(['curl' => [CURLOPT_TCP_KEEPALIVE => 1]])
    );
    $request = observe((new GateRequest('/json'))->setClient($client), static function (): void {
    });
    $result = ($async ? $request->sendAsync()->wait() : $request->send())->raw();
    check($result->errors->first()->code->value === 'configuration_error', 'customCurl отказ');
    check(RefreshingAuthenticator::$refreshCalls === 0, 'customCurl отказ до auth dependency');
    $plain = (new GateRequest('/json'))->setClient($client);
    check(($async ? $plain->sendAsync()->wait() : $plain->send())->raw()->isSuccess(), 'customCurl без opt-in работает');
    report("$mode/customCurl", ['guardBeforeAuth' => true]);
    $server->close();

    // Auth recovery назначает второй фактический номер, хотя ordinary retry выключен.
    $server = new LocalFileServer();
    RefreshingAuthenticator::reset();
    $client = new TestClient(new ClientConfig(baseUrl: $server->url, auth: new RefreshingAuthenticator()), transport());
    $seen = [];
    $request = observe((new GateRequest('/auth-retry'))->setClient($client), static function (TransferProgress $p) use (&$seen): void {
        $seen[$p->trace->executionId][$p->attempt] = $p;
    });
    $result = ($async ? $request->sendAsync()->wait() : $request->send())->raw();
    check($result->isSuccess() && RefreshingAuthenticator::$refreshCalls === 1, 'auth recovery');
    check(count($seen) === 1 && array_keys($seen[$result->trace->executionId]) === [1, 2], 'auth не наследует callback; исходный request имеет 1/2');
    foreach ($seen[$result->trace->executionId] as $snapshot) {
        check($snapshot->trace === $result->trace, 'trace auth retry');
    }
    $attempts = array_values(array_map(static fn ($e) => $e->context['attempt'], array_filter(
        $result->audit,
        static fn ($e): bool => $e->stage->value === 'http_request'
    )));
    check($attempts === [1, 2], 'совпадение с каноническим журналом');
    report("$mode/auth", ['attempts' => $attempts]);
    $server->close();

    // Poll получает собственные опции, а не callback исходной операции.
    $server = new LocalFileServer();
    $client = new TestClient(new ClientConfig(
        baseUrl: $server->url,
        continuationTokenExtractor: new TokenExtractor(),
    ), transport());
    $seen = [];
    $request = observe(
        (new ContinuationStartRequest('fixture'))->setClient($client),
        static function (TransferProgress $p) use (&$seen): void {
            $seen[$p->trace->executionId] = $p;
        }
    )->asProviderAsync();
    $handle = $async ? $request->sendAsync()->wait() : $request->send();
    $dto = $handle->await(new ContinuationAwaitOptions(maxAttempts: 1, intervalMs: 0));
    check($dto->value === 'done' && count($seen) === 1, 'poll не наследует callback');
    check(isset($seen[$handle->raw()->trace->executionId]), 'callback принадлежит исходному исполнению');
    report("$mode/poll", ['observedExecutions' => count($seen)]);
    $server->close();

    $server = new LocalFileServer();
    $client = new TestClient(new ClientConfig(baseUrl: $server->url, retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false)), transport());
    $seen = [];
    $sink = Utils::streamFor('');
    $request = observe((new GateDownloadRequest('/retry-download?size=100000'))->setClient($client), static function (TransferProgress $p) use (&$seen): void {
        $seen[$p->attempt] = $p;
    })->withDownloadTo($sink);
    $result = ($async ? $request->sendAsync()->wait() : $request->send())->raw();
    check($result->isSuccess() && array_keys($seen) === [1, 2], 'ordinary retry');
    check($seen[1]->downloaded === 3 && $seen[2]->downloaded === 100000, 'счётчики попыток не складываются');
    $result->data->close();
    check($sink->isWritable() && (string) $sink === str_repeat('Z', 100000), 'чужой sink открыт, попытки не склеены');
    $sink->close();
    report("$mode/retry", ['attempts' => array_keys($seen)]);
    $server->close();
}

// Исключение sync callback изолировано до возврата в native cURL.
$server = new LocalFileServer();
$client = new TestClient(new ClientConfig(baseUrl: $server->url), transport());
$calls = 0;
$result = observe(
    (new GateDownloadRequest('/download?size=3000000'))->setClient($client),
    static function () use (&$calls): void {
        ++$calls;
        throw new RuntimeException('fixture sync callback');
    }
)
    ->send()->raw();
check($result->isSuccess() && $calls === 1 && $result->data->size() === 3000000, 'sync callback не теряет ответ');
$result->data->close();
$server->close();
report('sync/exception', ['calls' => $calls]);

// Один multi, два исполнения, разные trace; ошибка одного получателя не прерывает HTTP.
$serverA = new LocalFileServer();
$serverB = new LocalFileServer();
$shared = transport();
$clientA = new TestClient(new ClientConfig(baseUrl: $serverA->url, retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false)), $shared);
$clientB = new TestClient(new ClientConfig(baseUrl: $serverB->url), $shared);
$failedCalls = 0;
$firstA = null;
$seenB = [];
$local = new ExecutionLocal();
$leave = $local->enter((object) ['owner' => 'application']);
try {
    $a = observe((new GateDownloadRequest('/retry-download?size=3000000'))->setClient($clientA), static function (TransferProgress $p) use (&$failedCalls, &$firstA): void {
        $firstA = $p;
        ++$failedCalls;
        throw new RuntimeException('fixture callback A');
    })->withTraceId('trace-a')->sendAsync();
    $b = observe((new GateDownloadRequest('/download?size=3000000'))->setClient($clientB), static function (TransferProgress $p) use (&$seenB, $local): void {
        $seenB[] = [$p, $local->get()];
    })->withTraceId('trace-b')->sendAsync();
    $ra = $a->wait()->raw();
    $rb = $b->wait()->raw();
    check($ra->isSuccess() && $rb->isSuccess() && $failedCalls === 1, 'ошибка callback A не отказала ни одной передаче и не вернулась при retry');
    check(count($seenB) > 1, 'соседняя передача продолжает уведомления');
    check($firstA->trace === $ra->trace && $firstA->attempt === 1, 'каноническая атрибуция отказавшего callback');
    foreach ($seenB as [$p, $ambient]) {
        check($p->trace === $rb->trace && $p->trace->traceId === 'trace-b' && $p->attempt === 1, 'захваченный trace B');
    }
    report('multi/exception', ['failedCallbackCalls' => $failedCalls, 'neighborEvents' => count($seenB),
        'ambient' => array_values(array_unique(array_map(static fn ($row) => $row[1]?->owner ?? 'null', $seenB)))]);
    $ra->data->close();
    $rb->data->close();
} finally {
    $leave();
    $serverA->close();
    $serverB->close();
}

// Пагинация переносит callable, но отключение после ошибки принадлежит странице.
$server = new LocalFileServer();
$client = new TestClient(new ClientConfig(baseUrl: $server->url), transport());
$pages = [];
$request = observe((new GatePaginatedRequest())->setClient($client), static function (TransferProgress $p) use (&$pages): void {
    $pages[$p->trace->executionId] = $p;
    throw new RuntimeException('fixture page callback');
});
$result = $request->paginate()->pages(3);
report('pagination/probe', ['executions' => count($pages), 'status' => $result->status->value, 'error' => $result->exception?->getMessage(), 'pages' => count($result->pages()->all())]);
check(count($pages) === 3, 'callback отдельно на каждой странице');
foreach ($pages as $p) {
    check($p->trace->parentExecutionId !== null && $p->attempt === 1, 'trace страницы');
}
report('pagination', ['executions' => count($pages)]);
$server->close();

// Отмена и abandon выполняются вне callback, уже после начавшейся передачи.
foreach (['cancel', 'abandon'] as $mode) {
    $serverA = new LocalFileServer();
    $serverB = new LocalFileServer();
    $shared = transport();
    $logger = new MemoryLogger();
    $clientA = new TestClient(new ClientConfig(baseUrl: $serverA->url, logger: $logger), $shared);
    $clientB = new TestClient(new ClientConfig(baseUrl: $serverB->url), $shared);
    $count = 0;
    $started = false;
    $afterStop = null;
    $observer = new stdClass();
    $weak = WeakReference::create($observer);
    $directory = tempnam(sys_get_temp_dir(), 'apisutra-progress-target-');
    unlink($directory);
    mkdir($directory);
    $target = $directory . '/target';
    file_put_contents($target, 'original');
    $callback = static function (TransferProgress $p) use (&$count, &$started, $observer): void {
        ++$count;
        $started = $started || $p->downloaded > 0;
        $observer->last = $p;
    };
    $request = observe((new GateDownloadRequest('/slow?size=16000000'))->setClient($clientA), $callback)
        ->withDownloadTo($target, overwrite: true);
    $a = $request->sendAsync();
    unset($request, $callback, $observer);
    $neighborCount = 0;
    $b = observe((new GateDownloadRequest('/slow?size=1500000'))->setClient($clientB), static function () use (&$neighborCount): void {
        ++$neighborCount;
    })->sendAsync();
    $timer = EventLoop::repeat(0.002, static function (string $id) use (&$a, $mode, &$started, &$afterStop, &$count): void {
        if ($started) {
            EventLoop::cancel($id);
            if ($mode === 'cancel') {
                $a->cancel();
            } else {
                $a = null;
            }
            $afterStop = $count;
        }
    });
    $neighbor = $b->wait()->raw();
    check($neighbor->isSuccess() && $neighborCount > 1 && $afterStop !== null, 'отмена после HTTP и сохранение соседа');
    check($count === $afterStop, 'нет поздних уведомлений');
    check(scandir($directory) === ['.', '..', 'target'], 'временный файл отменённой передачи удалён');
    foreach (get_resources('stream') as $resource) {
        check(!str_starts_with(stream_get_meta_data($resource)['uri'] ?? '', $directory . '/'), 'sink закрыт');
    }
    check(file_get_contents($target) === 'original', 'скачивание не заменило целевой файл');
    $neighbor->data->close();
    unset($a, $b, $clientA, $clientB, $shared);
    gc_collect_cycles();
    check($weak->get() === null, 'получатель освобождён после удаления handle и options');
    check(EventLoop::getIdentifiers() === [], 'нет оставшихся watchers');
    $terminals = array_values(array_filter($logger->records, static fn ($r): bool => in_array($r['context']['event'] ?? '', ['failed', 'abandoned', 'completed'], true)));
    check(count($terminals) === 1 && $terminals[0]['context']['event'] === ($mode === 'cancel' ? 'failed' : 'abandoned'), 'ровно один правильный терминал');
    report('cleanup/' . $mode, ['eventsAtStop' => $afterStop, 'neighborEvents' => $neighborCount, 'watchers' => 0, 'cancelledSinkClosed' => true, 'terminals' => count($terminals)]);
    unlink($target);
    rmdir($directory);
    $serverA->close();
    $serverB->close();
}
check(EventLoop::getIdentifiers() === [], 'последний loop пуст');
report('gate', ['status' => 'passed']);
