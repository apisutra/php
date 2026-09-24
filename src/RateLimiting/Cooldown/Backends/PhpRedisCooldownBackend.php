<?php

declare(strict_types=1);

namespace ApiSutra\RateLimiting\Cooldown\Backends;

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\RateLimiting\CooldownBackendException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Localization\Message;
use ApiSutra\RateLimiting\Cooldown\CooldownBackendInterface;
use ApiSutra\RateLimiting\Cooldown\CooldownUpdate;
use ApiSutra\RateLimiting\Internal\PhpRedisCommandRunner;
use Closure;
use Redis;
use Throwable;

final readonly class PhpRedisCooldownBackend implements CooldownBackendInterface
{
    private const int MAX_EXACT_INTEGER = 9_007_199_254_740_991;
    private PhpRedisCommandRunner $runner;
    private string $prefix;
    private string $script;

    public function __construct(object $redis, string $scope = 'default', int $ioTimeoutMs = 1000)
    {
        if ($scope === '') {
            throw new ConfigurationException(new Message('rate_limit.invalid_cooldown_scope'));
        }
        $this->runner = new PhpRedisCommandRunner($redis, $ioTimeoutMs);
        $script = file_get_contents(__DIR__ . '/../Resources/extend.lua');
        if ($script === false) {
            throw new ConfigurationException(new Message('rate_limit.cooldown_resource_missing'));
        }
        $this->script = $script;
        $this->prefix = 'apisutra:cooldown:v1:' . hash('sha256', $scope) . ':';
    }

    public function remainingMs(string $key, ?int $timeoutMs = null): int
    {
        return $this->guard('cooldown_read', function () use ($key, $timeoutMs): int {
            $reply = $this->runner->run(
                fn (Redis $redis): mixed => $redis->pttl($this->key($key)),
                $timeoutMs,
                'cooldown_read',
            );
            if ($reply === -2) {
                return 0;
            }
            if (!is_int($reply) || $reply < 0 || $reply > self::MAX_EXACT_INTEGER) {
                throw new CooldownBackendException('cooldown_read');
            }
            return $reply;
        });
    }

    public function extend(string $key, int $delayMs, ?int $timeoutMs = null): CooldownUpdate
    {
        if ($delayMs <= 0 || $delayMs > self::MAX_EXACT_INTEGER) {
            throw new ConfigurationException(new Message('rate_limit.invalid_cooldown_duration'));
        }
        $started = PhpRedisCommandRunner::nowMs();
        return $this->guard('cooldown_publish', function () use ($key, $delayMs, $timeoutMs, $started): CooldownUpdate {
            $reply = $this->runner->evaluate(
                $this->script,
                fn (): array => [$this->key($key), (string) max(0, $delayMs - (PhpRedisCommandRunner::nowMs() - $started))],
                1,
                $timeoutMs,
                'cooldown_publish',
            );
            if (
                !is_array($reply) || !array_is_list($reply) || count($reply) !== 2
                || !in_array($reply[0], [0, 1], true) || !is_int($reply[1])
                || $reply[1] < 0 || $reply[1] > self::MAX_EXACT_INTEGER
                || ($reply[0] === 1 && $reply[1] === 0)
            ) {
                throw new CooldownBackendException('cooldown_publish');
            }
            return new CooldownUpdate($reply[0] === 1, $reply[1]);
        });
    }

    private function key(string $key): string
    {
        return $this->prefix . hash('sha256', $key);
    }

    /**
     * @template T
     * @param Closure(): T $operation
     * @return T
     */
    private function guard(string $stage, Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (ConfigurationException | ExecutionDeadlineException | CooldownBackendException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CooldownBackendException($stage, $exception);
        }
    }
}
