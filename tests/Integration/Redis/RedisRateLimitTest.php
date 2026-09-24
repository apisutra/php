<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use ApiSutra\RateLimiting\Backends\PhpRedisRateLimitBackend;
use ApiSutra\RateLimiting\RateLimitQuota;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

beforeEach(function (): void {
    if (getenv('APISUTRA_TEST_REDIS') !== '1') {
        test()->markTestSkipped('Требуется выделенный Redis test server и APISUTRA_TEST_REDIS=1');
    }
    expect(extension_loaded('redis'))->toBeTrue();
    $this->redis = quotaRedis();
    $this->scope = 'test-' . bin2hex(random_bytes(12));
});

afterEach(function (): void {
    if (isset($this->redis, $this->scope)) {
        foreach ($this->redis->keys('apisutra:rate-limit:v3:' . hash('sha256', $this->scope) . ':*') as $key) {
            $this->redis->del($key);
        }
        $this->redis->close();
    }
});

function quotaRedis(): Redis
{
    $redis = new Redis();
    $redis->connect(getenv('APISUTRA_REDIS_HOST') ?: '127.0.0.1', (int) (getenv('APISUTRA_REDIS_PORT') ?: 6379), 2);
    return $redis;
}

function quotaKey(string $scope, string $key): string
{
    return 'apisutra:rate-limit:v3:' . hash('sha256', $scope) . ':' . hash('sha256', $key);
}

function runQuotaWorkers(string $scope, int $limit, int $period = 60000): array
{
    $barrier = sys_get_temp_dir() . '/apisutra-barrier-' . bin2hex(random_bytes(8));
    mkdir($barrier);
    $workers = [];
    try {
        for ($i = 0; $i < 8; $i++) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/../../Support/redis-quota-worker.php',
                getenv('APISUTRA_REDIS_HOST') ?: '127.0.0.1', getenv('APISUTRA_REDIS_PORT') ?: '6379',
                $scope, $barrier, (string) $i, (string) $limit, (string) $period],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $workers[] = [$process, $pipes];
        }
        $deadline = microtime(true) + 10;
        while (count(glob($barrier . '/*.ready')) < 8) {
            if (microtime(true) > $deadline) { throw new RuntimeException('Workers не дошли до барьера'); }
            usleep(1000);
        }
        file_put_contents($barrier . '/go', '1');
        $results = [];
        foreach ($workers as [$process, $pipes]) {
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            expect(proc_close($process))->toBe(0, $error);
            $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        }
        return $results;
    } finally {
        foreach ($workers as [$process, $pipes]) {
            if (is_resource($process)) { proc_terminate($process); proc_close($process); }
            foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
        }
        foreach (glob($barrier . '/*') as $file) { unlink($file); }
        rmdir($barrier);
    }
}

it('выдаёт ровно один общий слот восьми отдельным workers', function (): void {
    $results = runQuotaWorkers($this->scope, 1);
    expect(count(array_filter($results, fn ($r) => $r['granted'])))->toBe(1)
        ->and(array_unique(array_column($results, 'wallTime')))->toHaveCount(8);
});

it('операции одновременно соблюдают общий и собственные пределы', function (): void {
    $results = runQuotaWorkers($this->scope, 3);
    expect(count(array_filter($results, fn ($r) => $r['granted'])))->toBe(3);
    foreach ([0, 1] as $operation) {
        $value = $this->redis->get(quotaKey($this->scope, 'operation-' . $operation));
        expect((int) explode('|', $value)[3])->toBeLessThanOrEqual(2);
    }
});

it('не расходует свободную квоту при deny, дедуплицирует ID и разделяет scope', function (): void {
    $backend = new PhpRedisRateLimitBackend($this->redis, $this->scope);
    $a = new RateLimitQuota('a', 1, 60000);
    $b = new RateLimitQuota('b', 1, 60000);
    expect($backend->tryAcquire([$a, $a])->granted)->toBeTrue();
    $before = $this->redis->get(quotaKey($this->scope, 'a'));
    expect($backend->tryAcquire([$a, $b])->blockedIds)->toBe(['a'])
        ->and($this->redis->get(quotaKey($this->scope, 'a')))->toBe($before)
        ->and($this->redis->exists(quotaKey($this->scope, 'b')))->toBe(0)
        ->and($backend->tryAcquire([$b])->granted)->toBeTrue();
    $other = new PhpRedisRateLimitBackend($this->redis, $this->scope . '-other');
    try { expect($other->tryAcquire([$a])->granted)->toBeTrue(); }
    finally { $this->redis->del(quotaKey($this->scope . '-other', 'a')); }
});

it('новое окно после истечения снова общее для workers', function (): void {
    $quota = new RateLimitQuota('common', 1, 20);
    $backend = new PhpRedisRateLimitBackend($this->redis, $this->scope);
    $backend->tryAcquire([$quota]);
    $deadline = microtime(true) + 2;
    while ($this->redis->exists(quotaKey($this->scope, 'common'))) {
        if (microtime(true) > $deadline) { throw new RuntimeException('TTL не истёк'); }
        usleep(1000);
    }
    $results = runQuotaWorkers($this->scope, 1);
    expect(count(array_filter($results, fn ($r) => $r['granted'])))->toBe(1);
});

it('восстанавливает NOSCRIPT и не выдаёт двойного разрешения', function (): void {
    $backend = new PhpRedisRateLimitBackend($this->redis, $this->scope);
    $quota = new RateLimitQuota('a', 2, 60000);
    $backend->tryAcquire([$quota]);
    $this->redis->script('flush');
    expect($backend->tryAcquire([$quota])->granted)->toBeTrue()->and($backend->tryAcquire([$quota])->granted)->toBeFalse();
});

it('сохраняет точные большие числа без потери JSON precision', function (): void {
    $backend = new PhpRedisRateLimitBackend($this->redis, $this->scope);
    $quota = new RateLimitQuota('a', 9007199254740991, 60000);
    expect($backend->tryAcquire([$quota])->granted)->toBeTrue()->and($backend->tryAcquire([$quota])->granted)->toBeTrue();
    expect($this->redis->get(quotaKey($this->scope, 'a')))->toStartWith('v1|9007199254740991|60000|2|');
});

it('не сбрасывает конфликтующее определение и не пишет вторую квоту', function (): void {
    $backend = new PhpRedisRateLimitBackend($this->redis, $this->scope);
    $backend->tryAcquire([new RateLimitQuota('a', 1, 60000)]);
    expect(fn () => $backend->tryAcquire([new RateLimitQuota('b', 1, 60000), new RateLimitQuota('a', 2, 60000)]))
        ->toThrow(ConfigurationException::class);
    expect($this->redis->exists(quotaKey($this->scope, 'b')))->toBe(0);
});

it('некорректное состояние и wrong type не превращаются в новое окно', function (string $state): void {
    $key = quotaKey($this->scope, 'a');
    if ($state === 'list') { $this->redis->lPush($key, 'fixture'); }
    else { $this->redis->set($key, $state); }
    $backend = new PhpRedisRateLimitBackend($this->redis, $this->scope);
    expect(fn () => $backend->tryAcquire([new RateLimitQuota('b', 1, 60000), new RateLimitQuota('a', 1, 60000)]))
        ->toThrow(RateLimitBackendException::class);
    expect($this->redis->exists(quotaKey($this->scope, 'b')))->toBe(0);
})->with(['broken', 'list', 'v1|1|60000|NaN|1']);

it('проверяет ошибку до MSET и после MSET настоящими ACL', function (string $denied, bool $written): void {
    $user = $this->scope;
    $this->redis->rawCommand('ACL', 'SETUSER', $user, 'on', '>fixture-password', '~*', '+@all', '-' . $denied);
    try {
        $connection = quotaRedis();
        $connection->auth([$user, 'fixture-password']);
        $backend = new PhpRedisRateLimitBackend($connection, $this->scope);
        expect(fn () => $backend->tryAcquire([new RateLimitQuota('a', 3, 60000), new RateLimitQuota('b', 3, 60000)]))
            ->toThrow(RateLimitBackendException::class);
        foreach (['a', 'b'] as $key) {
            $value = $this->redis->get(quotaKey($this->scope, $key));
            if ($written) { expect($value)->toStartWith('v1|3|60000|1|'); }
            else { expect($value)->toBeFalse(); }
        }
        $connection->close();
    } finally { $this->redis->rawCommand('ACL', 'DELUSER', $user); }
})->with([['mset', false], ['pexpireat', true]]);

it('OOM не допускает allow или fallback', function (): void {
    $backend = new PhpRedisRateLimitBackend($this->redis, $this->scope);
    $old = $this->redis->config('GET', 'maxmemory')['maxmemory'];
    try {
        $this->redis->config('SET', 'maxmemory', '1');
        expect(fn () => $backend->tryAcquire([new RateLimitQuota('a', 1, 60000)]))->toThrow(RateLimitBackendException::class);
    } finally { $this->redis->config('SET', 'maxmemory', $old); }
    expect($this->redis->exists(quotaKey($this->scope, 'a')))->toBe(0);
});

it('потеря ответа после записи не повторяет acquire и не отправляет HTTP', function (): void {
    // Прогреваем script cache до подключения proxy; потери подтверждения не смешиваются с NOSCRIPT.
    (new PhpRedisRateLimitBackend($this->redis, $this->scope))->tryAcquire([new RateLimitQuota('warm', 1, 60000)]);
    $process = proc_open([PHP_BINARY, __DIR__ . '/../../Support/redis-drop-reply-proxy.php',
        getenv('APISUTRA_REDIS_HOST') ?: '127.0.0.1', getenv('APISUTRA_REDIS_PORT') ?: '6379'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    try {
        $address = trim(fgets($pipes[1]));
        $redis = new Redis();
        $redis->connect('127.0.0.1', (int) substr($address, strrpos($address, ':') + 1), 2);
        $backend = new PhpRedisRateLimitBackend($redis, $this->scope, 500);
        $transport = new MockTransport();
        $client = new TestClient(new ClientConfig(baseUrl: 'https://quota.test', rateLimit: new RateLimitConfig(),
            rateLimitBackend: $backend, retry: new RetryConfig(attempts: 3, retryExceptions: [Throwable::class])), $transport);
        $result = $client->send(new RetryPolicyRequest())->raw();
        expect($result->errors->first()?->code)->toBe(ErrorCode::ExecutionError)
            ->and($transport->getRecorded())->toBe([]);
        $ack = json_decode(trim(stream_get_contents($pipes[1])), true, flags: JSON_THROW_ON_ERROR);
        expect($ack['forwarded'])->toBe(1)->and($ack['reply'])->toBe("*1\r\n:1\r\n");
        $logicalKey = hash('sha256', 'apisutra.rate-limit.v3:client:client');
        expect($this->redis->get(quotaKey($this->scope, $logicalKey)))->toStartWith('v1|100|60000|1|');
        $error = stream_get_contents($pipes[2]);
        expect(proc_close($process))->toBe(0, $error);
    } finally {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
    }
});

it('поддерживает prefix, отключает retry и восстанавливает read timeout', function (): void {
    $this->redis->setOption(Redis::OPT_PREFIX, 'laravel-fixture:');
    $this->redis->setOption(Redis::OPT_MAX_RETRIES, 3);
    $this->redis->setOption(Redis::OPT_READ_TIMEOUT, 2.5);
    $backend = new PhpRedisRateLimitBackend($this->redis, $this->scope);
    try {
        expect($backend->tryAcquire([new RateLimitQuota('a', 1, 60000)])->granted)->toBeTrue()
            ->and($backend->tryAcquire([new RateLimitQuota('a', 1, 60000)])->granted)->toBeFalse()
            ->and($this->redis->getOption(Redis::OPT_MAX_RETRIES))->toBe(0)
            ->and($this->redis->getOption(Redis::OPT_READ_TIMEOUT))->toBe(2.5);
        $this->redis->multi(Redis::PIPELINE);
        expect(fn () => $backend->tryAcquire([new RateLimitQuota('a', 1, 60000)]))->toThrow(ConfigurationException::class);
        $this->redis->discard();
    } finally {
        $this->redis->del(quotaKey($this->scope, 'a'));
        $this->redis->setOption(Redis::OPT_PREFIX, '');
    }
});

it('отклоняет serializer и закрытое соединение не заменяет памятью', function (): void {
    $backend = new PhpRedisRateLimitBackend($this->redis, $this->scope);
    $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
    expect(fn () => $backend->tryAcquire([new RateLimitQuota('a', 1, 60000)]))->toThrow(ConfigurationException::class);
    $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
    $this->redis->close();
    expect(fn () => $backend->tryAcquire([new RateLimitQuota('a', 1, 60000)]))->toThrow(RateLimitBackendException::class);
    $this->redis = quotaRedis();
});

it('применяет сетевой timeout и общий deadline до HTTP', function (): void {
    $connection = quotaRedis();
    $backend = new PhpRedisRateLimitBackend($connection, $this->scope, 500);
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://quota.test', rateLimit: new RateLimitConfig(),
        rateLimitBackend: $backend, retry: new RetryConfig(totalTimeoutMs: 30)), $transport);
    // Изолированный сервер остановит исполнение команды дольше бюджета вызова.
    $this->redis->rawCommand('CLIENT', 'PAUSE', 250, 'ALL');
    $started = hrtime(true);
    $result = $client->send(new RetryPolicyRequest())->raw();
    expect($result->errors->first()?->code)->toBe(ErrorCode::Timeout)->and($transport->getRecorded())->toBe([])
        ->and((hrtime(true) - $started) / 1_000_000)->toBeLessThan(2000);
    // Ждём восстановления сервера настоящей командой, без догадки по sleep.
    $this->redis->ping();
});
