<?php

declare(strict_types=1);

namespace ApiSutra\RateLimiting\Backends;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\RateLimiting\RateLimitBackendInterface;
use ApiSutra\RateLimiting\RateLimitDecision;
use ApiSutra\RateLimiting\RateLimitQuota;
use ApiSutra\RateLimiting\Internal\PhpRedisCommandRunner;
use Throwable;

final class PhpRedisRateLimitBackend implements RateLimitBackendInterface
{
    private const int MAX_EXACT_INTEGER = 9_007_199_254_740_991;
    private readonly PhpRedisCommandRunner $runner;
    private readonly string $prefix;
    private readonly string $script;

    /** Выделенное готовое соединение принадлежит адаптеру; автоматические повторы отключаются. */
    public function __construct(object $redis, string $scope, int $ioTimeoutMs = 1000)
    {
        if ($scope === '') {
            throw new ConfigurationException(new Message('ratelimiting.redis_rate_limit_requires_a_non_empty_scope_and'));
        }
        $this->runner = new PhpRedisCommandRunner($redis, $ioTimeoutMs);
        $script = file_get_contents(__DIR__ . '/../Resources/acquire.lua');
        if ($script === false) {
            throw new ConfigurationException(new Message('ratelimiting.rate_limit_lua_resource_not_found'));
        }
        $this->script = $script;
        $this->prefix = 'apisutra:rate-limit:v3:' . hash('sha256', $scope) . ':';
    }

    public function tryAcquire(array $quotas, ?int $timeoutMs = null): RateLimitDecision
    {
        $quotas = RateLimitQuota::normalize($quotas);
        if ($quotas === []) {
            return new RateLimitDecision(true);
        }
        $keys = [];
        $arguments = [];
        foreach ($quotas as $quota) {
            if ($quota->limit > self::MAX_EXACT_INTEGER || $quota->periodMs > self::MAX_EXACT_INTEGER) {
                throw new ConfigurationException(new Message('ratelimiting.quota_value_is_outside_the_exact_integer_range_of'));
            }
            $keys[] = $this->prefix . hash('sha256', $quota->key);
            $arguments[] = (string) $quota->limit;
            $arguments[] = (string) $quota->periodMs;
        }
        try {
            $reply = $this->runner->evaluate(
                $this->script,
                static fn (): array => [...$keys, ...$arguments],
                count($keys),
                $timeoutMs,
                'rate_limit_store',
            );
            return $this->decision($reply, $quotas);
        } catch (ConfigurationException | ExecutionDeadlineException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $exception instanceof RateLimitBackendException ? $exception : new RateLimitBackendException($exception);
        }
    }

    /** @param list<RateLimitQuota> $quotas */
    private function decision(mixed $reply, array $quotas): RateLimitDecision
    {
        if ($reply === [1]) {
            return new RateLimitDecision(true);
        }
        if ($reply === [-3]) {
            throw new ConfigurationException(new Message('ratelimiting.redis_quota_window_end_cannot_be_represented_exactly'));
        }
        if ($reply === [-1]) {
            throw new ConfigurationException(new Message('ratelimiting.active_redis_quota_definition_changed'));
        }
        if (!is_array($reply) || !array_is_list($reply) || count($reply) < 3 || $reply[0] !== 0 || !is_int($reply[1])) {
            throw new RateLimitBackendException();
        }
        $blocked = [];
        foreach (array_slice($reply, 2) as $index) {
            if (!is_int($index) || $index < 1 || !isset($quotas[$index - 1])) {
                throw new RateLimitBackendException();
            }
            $blocked[] = $quotas[$index - 1]->key;
        }
        return new RateLimitDecision(false, $blocked, $reply[1]);
    }
}
