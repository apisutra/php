<?php

declare(strict_types=1);

namespace ApiSutra\RateLimiting\Internal;

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Localization\Message;
use Closure;
use Redis;
use RedisException;
use RuntimeException;

/** @internal Только ограниченный I/O; ключи, Lua и доменные ошибки принадлежат адаптерам. */
final readonly class PhpRedisCommandRunner
{
    private Redis $redis;

    public function __construct(object $redis, private int $ioTimeoutMs)
    {
        if (!extension_loaded('redis') || !$redis instanceof Redis || version_compare((string) phpversion('redis'), '6.2', '<')) {
            throw new ConfigurationException(new Message('ratelimiting.phpredis_backend_required'));
        }
        if ($ioTimeoutMs < 1 || $ioTimeoutMs > intdiv(PHP_INT_MAX, 1_000_000)) {
            throw new ConfigurationException(new Message('ratelimiting.invalid_redis_io_timeout'));
        }
        $this->redis = $redis;
        if (!$redis->isConnected()) {
            throw new ConfigurationException(new Message('ratelimiting.provide_an_open_dedicated_redis_connection'));
        }
        $redis->setOption(Redis::OPT_MAX_RETRIES, 0);
        $this->checkOptions();
        // Возврат READ_TIMEOUT=0 через setOption ломает следующее чтение в phpredis 6.2.
        if ($redis->getOption(Redis::OPT_READ_TIMEOUT) <= 0 && !$redis->setOption(Redis::OPT_READ_TIMEOUT, $ioTimeoutMs / 1000)) {
            throw new ConfigurationException(new Message('ratelimiting.failed_to_set_a_finite_redis_read_timeout'));
        }
    }

    /**
     * @template T
     * @param Closure(Redis, Closure(): void): T $command
     * @return T
     */
    public function run(Closure $command, ?int $timeoutMs, string $stage): mixed
    {
        $duration = min($this->ioTimeoutMs, $timeoutMs ?? $this->ioTimeoutMs);
        if ($duration <= 0) {
            throw new ExecutionDeadlineException($stage);
        }
        $deadline = self::nowMs() + $duration;
        if (!$this->redis->isConnected()) {
            throw new RuntimeException('Redis connection is unavailable.');
        }
        $this->checkOptions();
        $oldTimeout = $this->redis->getOption(Redis::OPT_READ_TIMEOUT);
        try {
            $refresh = fn () => $this->setTimeout($deadline);
            $refresh();
            $reply = $command($this->redis, $refresh);
            if (self::nowMs() >= $deadline) {
                throw new RuntimeException('Redis I/O deadline exceeded.');
            }
            return $reply;
        } finally {
            if (!$this->redis->setOption(Redis::OPT_READ_TIMEOUT, $oldTimeout)) {
                throw new RuntimeException('Redis read timeout could not be restored.');
            }
        }
    }

    /** @param Closure(): list<string> $arguments */
    public function evaluate(string $script, Closure $arguments, int $keyCount, ?int $timeoutMs, string $stage): mixed
    {
        return $this->run(static function (Redis $redis, Closure $refresh) use ($script, $arguments, $keyCount): mixed {
            $redis->clearLastError();
            try {
                $reply = $redis->evalSha(sha1($script), $arguments(), $keyCount);
                if ($reply !== false) {
                    return $reply;
                }
                $error = $redis->getLastError();
                if (!is_string($error) || !str_starts_with($error, 'NOSCRIPT ')) {
                    throw new RuntimeException('Redis script failed.');
                }
            } catch (RedisException $exception) {
                if (!str_starts_with($exception->getMessage(), 'NOSCRIPT ')) {
                    throw $exception;
                }
            }
            // Только NOSCRIPT доказывает отсутствие записи. Аргументы времени вычисляются заново.
            $refresh();
            return $redis->eval($script, $arguments(), $keyCount);
        }, $timeoutMs, $stage);
    }

    public static function nowMs(): int
    {
        return (int) (hrtime(true) / 1_000_000);
    }

    private function setTimeout(int $deadline): void
    {
        $remaining = $deadline - self::nowMs();
        if ($remaining <= 0 || !$this->redis->setOption(Redis::OPT_READ_TIMEOUT, $remaining / 1000)) {
            throw new RuntimeException('Redis I/O deadline exceeded.');
        }
    }

    private function checkOptions(): void
    {
        if (
            $this->redis->getMode() !== Redis::ATOMIC
            || $this->redis->getOption(Redis::OPT_SERIALIZER) !== Redis::SERIALIZER_NONE
            || $this->redis->getOption(Redis::OPT_COMPRESSION) !== Redis::COMPRESSION_NONE
            || $this->redis->getOption(Redis::OPT_MAX_RETRIES) !== 0
        ) {
            throw new ConfigurationException(new Message('ratelimiting.redis_backend_requires_normal_mode'));
        }
    }
}
