<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Execution\Async\AsyncRuntime;
use Revolt\EventLoop;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\RateLimiting\CooldownBackendException;
use ApiSutra\RateLimiting\Cooldown\Backends\PhpRedisCooldownBackend;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

beforeEach(function (): void {
    if (getenv('APISUTRA_TEST_REDIS') !== '1') { test()->markTestSkipped('Требуется выделенный Redis test server'); }
    $this->redis = cooldownRedis();
    $this->scope = 'cooldown-' . bin2hex(random_bytes(12));
});

afterEach(function (): void {
    if (!isset($this->redis)) { return; }
    foreach ($this->redis->keys('apisutra:cooldown:v1:' . hash('sha256', $this->scope) . ':*') as $key) { $this->redis->del($key); }
    $this->redis->close();
});

function cooldownRedis(): Redis
{
    $redis = new Redis();
    $redis->connect(getenv('APISUTRA_REDIS_HOST') ?: '127.0.0.1', (int) (getenv('APISUTRA_REDIS_PORT') ?: 6379), 2);
    return $redis;
}

function cooldownKey(string $scope, string $key): string
{
    return 'apisutra:cooldown:v1:' . hash('sha256', $scope) . ':' . hash('sha256', $key);
}

function cooldownWorker(string $scope, string $mode, int $argument): array
{
    $process = proc_open([PHP_BINARY, __DIR__ . '/../../Support/redis-cooldown-worker.php',
        getenv('APISUTRA_REDIS_HOST') ?: '127.0.0.1', getenv('APISUTRA_REDIS_PORT') ?: '6379', $scope, $mode, (string) $argument],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    return [$process, $pipes];
}

function cooldownWorkerResult(array $worker): array
{
    [$process, $pipes] = $worker;
    try {
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        expect(proc_close($process))->toBe(0, $error);
        return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    } finally {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
    }
}

it('процесс B видит 429 процесса A без переноса ответа и независимо от локальных часов', function (): void {
    $a = cooldownWorkerResult(cooldownWorker($this->scope, 'publish', 1_000_000));
    $b = cooldownWorkerResult(cooldownWorker($this->scope, 'read', 9_000_000));
    expect($a['http'])->toBe(1)->and($a['status'])->toBe(429)
        ->and($b['http'])->toBe(0)->and($b['status'])->toBeNull()->and($b['reason'])->toBe('server_cooldown_active')
        ->and($b['trace'])->not->toBe($a['trace']);
});

it('конкурирующие короткие записи не сокращают длинный TTL и NOSCRIPT сохраняет max', function (): void {
    $backend = new PhpRedisCooldownBackend($this->redis, $this->scope);
    $backend->extend('shared', 60_000);
    $workers = [];
    for ($i = 0; $i < 8; $i++) { $workers[] = cooldownWorker($this->scope, 'extend', 1000 + $i); }
    foreach ($workers as $worker) { expect(cooldownWorkerResult($worker)['extended'])->toBeFalse(); }
    $this->redis->script('flush');
    expect($backend->extend('shared', 500)->extended)->toBeFalse()->and($backend->remainingMs('shared'))->toBeGreaterThan(50_000);
    expect($backend->extend('shared', 90_000)->extended)->toBeTrue()->and($backend->remainingMs('shared'))->toBeGreaterThan(80_000);
});

it('истекает без фонового процесса и разделяет scope и prefix', function (): void {
    $backend = new PhpRedisCooldownBackend($this->redis, $this->scope);
    $backend->extend('short', 20);
    $otherConnection = cooldownRedis();
    $otherConnection->setOption(Redis::OPT_PREFIX, 'other:');
    $other = new PhpRedisCooldownBackend($otherConnection, $this->scope);
    expect($other->remainingMs('short'))->toBe(0)
        ->and((new PhpRedisCooldownBackend($this->redis, 'other'))->remainingMs('short'))->toBe(0);
    $deadline = microtime(true) + 2;
    while ($this->redis->exists(cooldownKey($this->scope, 'short'))) {
        if (microtime(true) >= $deadline) { throw new RuntimeException('TTL не истёк'); }
        usleep(1000);
    }
    expect($backend->remainingMs('short'))->toBe(0);
    $otherConnection->close();
});

it('ключ без TTL не даёт разрешение и не исправляется слепой записью', function (): void {
    $this->redis->set(cooldownKey($this->scope, 'broken'), '1');
    $backend = new PhpRedisCooldownBackend($this->redis, $this->scope);
    expect(fn () => $backend->remainingMs('broken'))->toThrow(CooldownBackendException::class)
        ->and(fn () => $backend->extend('broken', 1000))->toThrow(CooldownBackendException::class)
        ->and($this->redis->pttl(cooldownKey($this->scope, 'broken')))->toBe(-1);
});

it('восстанавливает timeout и запрещает неподходящий режим и fallback при обрыве', function (): void {
    $connection = cooldownRedis();
    $connection->setOption(Redis::OPT_READ_TIMEOUT, 2.5);
    $backend = new PhpRedisCooldownBackend($connection, $this->scope);
    expect($backend->remainingMs('absent', 100))->toBe(0)->and($connection->getOption(Redis::OPT_READ_TIMEOUT))->toBe(2.5);
    $backend->extend('key', 1000, 100);
    expect($connection->getOption(Redis::OPT_READ_TIMEOUT))->toBe(2.5)->and($connection->getOption(Redis::OPT_MAX_RETRIES))->toBe(0);
    $connection->multi(Redis::PIPELINE);
    expect(fn () => $backend->remainingMs('key'))->toThrow(ConfigurationException::class);
    $connection->discard();
    $connection->close();
    expect(fn () => $backend->remainingMs('key'))->toThrow(CooldownBackendException::class);
});

it('потеря подтверждения публикации сохраняет 429 и не повторяет HTTP или запись', function (): void {
    (new PhpRedisCooldownBackend($this->redis, $this->scope))->extend('warm', 60_000);
    $process = proc_open([PHP_BINARY, __DIR__ . '/../../Support/redis-drop-reply-proxy.php',
        getenv('APISUTRA_REDIS_HOST') ?: '127.0.0.1', getenv('APISUTRA_REDIS_PORT') ?: '6379', '3'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    try {
        $address = trim(fgets($pipes[1]));
        $connection = new Redis();
        $connection->connect('127.0.0.1', (int) substr($address, strrpos($address, ':') + 1), 2);
        $backend = new PhpRedisCooldownBackend($connection, $this->scope, 500);
        $transport = new MockTransport();
        $transport->fake(['*' => MockResponse::rateLimited(30)]);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', cooldownBackend: $backend,
            retry: new RetryConfig(attempts: 3, retryExceptions: [Throwable::class])), $transport);
        $result = $client->send(new RetryPolicyRequest())->raw();
        expect($result->response->status)->toBe(429)->and($transport->getRecorded())->toHaveCount(1)
            ->and($result->errors->first()->context)->toMatchArray(['reason' => 'cooldown_backend_error', 'stage' => 'cooldown_publish']);
        $ack = json_decode(trim(stream_get_contents($pipes[1])), true, flags: JSON_THROW_ON_ERROR);
        expect($ack['forwarded'])->toBe(3)->and($this->redis->keys('apisutra:cooldown:v1:' . hash('sha256', $this->scope) . ':*'))->toHaveCount(2);
        $error = stream_get_contents($pipes[2]);
        expect(proc_close($process))->toBe(0, $error);
    } finally {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
    }
});

it('отличает сетевой cap от общего дедлайна и восстанавливает timeout при сбое', function (?int $budget): void {
    $connection = cooldownRedis();
    $connection->setOption(Redis::OPT_READ_TIMEOUT, 2.5);
    $backend = new PhpRedisCooldownBackend($connection, $this->scope, 40);
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', cooldownBackend: $backend,
        retry: new RetryConfig(attempts: 1, totalTimeoutMs: $budget)), $transport);
    $this->redis->rawCommand('CLIENT', 'PAUSE', 250, 'ALL');
    $result = $client->send(new RetryPolicyRequest())->raw();
    expect($result->errors->first()->code)->toBe($budget === null ? ErrorCode::ExecutionError : ErrorCode::Timeout)
        ->and($result->errors->first()->context['stage'])->toBe('cooldown_read')
        ->and($transport->getRecorded())->toBe([])->and($connection->getOption(Redis::OPT_READ_TIMEOUT))->toBe(2.5);
    $this->redis->ping();
})->with([null, 20]);

it('замеряет реальные Redis-чтения при конкурентном HTTP и блокировку соседней Fiber', function (): void {
    $connection = cooldownRedis();
    $backend = new PhpRedisCooldownBackend($connection, $this->scope, 40);
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', cooldownBackend: $backend), $transport);
    $calls = function (): int {
        $stats = $this->redis->info('commandstats')['cmdstat_pttl'] ?? '';
        preg_match('/(?:^|,)calls=(\d+)/', $stats, $matches);
        return (int) ($matches[1] ?? 0);
    };
    $before = $calls();
    $summary = $client->pool(array_fill(0, 5, new RetryPolicyRequest()), 5)->consume();
    $after = $calls();
    expect($summary->successful)->toBe(5)->and($after - $before)->toBe(10);

    $runtime = new AsyncRuntime();
    $started = hrtime(true);
    $timerMs = null;
    $timer = $runtime->start(static function () use ($runtime, $started, &$timerMs): void {
        $runtime->sleep(1);
        $timerMs = (hrtime(true) - $started) / 1_000_000;
    });
    $this->redis->rawCommand('CLIENT', 'PAUSE', 250, 'ALL');
    $result = $client->sendAsync(new RetryPolicyRequest())->wait()->raw();
    $timer->wait();
    expect($result->errors->first()->context['reason'])->toBe('cooldown_backend_error')
        ->and($timerMs)->toBeGreaterThan(20)->toBeLessThan(2000);
    $this->redis->ping();
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
});

it('исполняет опубликованный Redis-пример в двух отдельных процессах', function (): void {
    $outputs = [];
    foreach (['publish', 'read'] as $mode) {
        $process = proc_open([PHP_BINARY, __DIR__ . '/../../../docs/example/cooldown/redis.php', $mode],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null,
            ['REDIS_HOST' => getenv('APISUTRA_REDIS_HOST') ?: '127.0.0.1', 'REDIS_PORT' => getenv('APISUTRA_REDIS_PORT') ?: '6379', 'APISUTRA_COOLDOWN_SCOPE' => $this->scope]);
        $outputs[] = cooldownWorkerResult([$process, $pipes]);
    }
    expect($outputs[0]['status'])->toBe(429)->and($outputs[1])->toBe(['http' => 0, 'status' => null, 'reason' => 'server_cooldown_active']);
});

it('поздняя публикация другого процесса не отменяет уже допущенный HTTP', function (): void {
    $backend = new PhpRedisCooldownBackend($this->redis, $this->scope);
    $transport = new MockTransport();
    $transport->fake(['*' => function (): MockResponse {
        // Барьер внутри уже допущенного транспорта: ждём публикацию другого процесса.
        $published = cooldownWorkerResult(cooldownWorker($this->scope, 'publish', 0));
        expect($published['status'])->toBe(429);
        return MockResponse::success();
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', cooldownBackend: $backend), $transport);
    expect($client->send(new RetryPolicyRequest())->raw()->isSuccess())->toBeTrue();
    $next = $client->send(new RetryPolicyRequest())->raw();
    expect($next->errors->first()->context['reason'])->toBe('server_cooldown_active')
        ->and($transport->getRecorded())->toHaveCount(1)->and($next->response)->toBeNull();
});
