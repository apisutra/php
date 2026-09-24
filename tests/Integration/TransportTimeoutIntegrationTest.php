<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Exceptions\Transport\TimeoutException;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\LocalTimeoutServer;
use ApiSutra\Transport\GuzzleHttpClient;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\HttpFactory;

beforeEach(function (): void {
    if (!extension_loaded('curl') || !function_exists('proc_open')) {
        $this->markTestSkipped('Локальный HTTP-стенд требует ext-curl и proc_open');
    }
});

it('прерывает реальный HTTP при медленных заголовках, теле и TLS handshake', function (string $mode): void {
    $server = new LocalTimeoutServer();
    $factory = new HttpFactory();
    $transport = new HttpTransport(new GuzzleHttpClient(['verify' => false]), $factory, $factory);
    $url = $mode === 'tls' ? $server->tlsUrl : $server->httpUrl . '/slow?ms=2000&body=' . $mode;
    $options = $mode === 'tls' ? new TransportOptions(5000, 100) : new TransportOptions(100, 5000);
    $start = hrtime(true);
    try {
        expect(fn () => $transport->send(new PreparedRequest(HttpMethod::GET, $url, transportOptions: $options)))
            ->toThrow(TimeoutException::class);
        expect((hrtime(true) - $start) / 1e6)->toBeLessThan(1500);
    } finally {
        $server->close();
    }
})->with(['headers', 'slow', 'tls']);

it('сохраняет независимые per-request настройки, явный ноль и HTTP статусы', function (): void {
    $server = new LocalTimeoutServer();
    $factory = new HttpFactory();
    $http = new GuzzleHttpClient(['timeout' => 0.05, 'connect_timeout' => 0.05]);
    $transport = new HttpTransport($http, $factory, $factory);
    try {
        $request = new PreparedRequest(HttpMethod::GET, $server->httpUrl . '/slow?ms=200', transportOptions: new TransportOptions(50, 50));
        expect(fn () => $transport->send($request))->toThrow(TimeoutException::class);
        expect($transport->send($request->with(transportOptions: new TransportOptions(1000, 1000)))->status)->toBe(200);
        expect($transport->send($request->with(transportOptions: new TransportOptions(0, 0)))->status)->toBe(200);
        // Прямой PSR-вызов использует неизменённую конфигурацию HTTP-клиента.
        expect(fn () => $transport->send($request->with(url: $server->httpUrl . '/slow?ms=200', transportOptions: new TransportOptions(50, 50))))->toThrow(TimeoutException::class);
        expect(fn () => $http->sendRequest($factory->createRequest('GET', $server->httpUrl . '/slow?ms=200')))->toThrow(ConnectException::class);
        foreach ([302, 503] as $status) {
            $response = $transport->send(new PreparedRequest(HttpMethod::GET, $server->httpUrl . '/ok?status=' . $status, transportOptions: new TransportOptions(1000, 1000)));
            expect($response->status)->toBe($status);
        }
    } finally {
        $server->close();
    }
});

it('соблюдает разные таймауты перекрывающихся HTTP вызовов из независимых процессов', function (): void {
    $server = new LocalTimeoutServer();
    $workers = [];
    try {
        foreach ([300, 2000] as $timeout) {
            // Bootstrap-диагностика зависимостей идёт в stderr, stdout содержит протокол worker.
            $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', dirname(__DIR__) . '/Support/timeout-http-worker.php', $server->httpUrl . '/slow?ms=800', (string) $timeout], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $workers[] = [$process, $pipes];
            stream_set_timeout($pipes[1], 5);
            expect(trim((string) fgets($pipes[1])))->toBe('ready');
        }
        foreach ($workers as [$process, $pipes]) {
            fwrite($pipes[0], "go\n");
            fflush($pipes[0]);
        }
        $results = [];
        foreach ($workers as [$process, $pipes]) {
            $results[] = json_decode((string) fgets($pipes[1]), true, flags: JSON_THROW_ON_ERROR);
        }
        expect($results[0]['outcome'])->toBe('timeout')->and($results[1]['outcome'])->toBe(200)
            ->and($results[0]['start'])->toBeLessThan($results[1]['end'])
            ->and($results[1]['start'])->toBeLessThan($results[0]['end']);
    } finally {
        foreach ($workers as [$process, $pipes]) {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
        $server->close();
    }
});

it('общий бюджет прерывает реальный HTTP при отключённом таймауте попытки', function (): void {
    $server = new LocalTimeoutServer();
    try {
        $client = new TestClient(new ClientConfig(baseUrl: $server->httpUrl, timeout: 0, connectTimeout: 0,
            retry: new RetryConfig(totalTimeoutMs: 150)), HttpTransport::createDefault());
        $start = hrtime(true);
        $result = (new CacheProbeRequest('/slow?ms=2000'))->setClient($client)->withoutRetry()->send()->raw();
        expect($result->exception)->toBeInstanceOf(ExecutionDeadlineException::class)
            ->and($result->exception->getPrevious())->not->toBeNull()
            ->and((hrtime(true) - $start) / 1e6)->toBeLessThan(1500);
    } finally {
        $server->close();
    }
});
