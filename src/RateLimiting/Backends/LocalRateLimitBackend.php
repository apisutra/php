<?php

declare(strict_types=1);

namespace ApiSutra\RateLimiting\Backends;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\RateLimiting\RateLimitBackendInterface;
use ApiSutra\RateLimiting\RateLimitDecision;
use ApiSutra\RateLimiting\RateLimitQuota;
use ApiSutra\Timing\SystemClock;

final class LocalRateLimitBackend implements RateLimitBackendInterface
{
    /** @var array<string, array{limit: int, periodMs: int, count: int, reset: int}> */
    private array $states = [];
    private ?int $nextExpiry = null;

    public function __construct(private readonly ClockInterface $clock = new SystemClock())
    {
    }

    public function tryAcquire(array $quotas, ?int $timeoutMs = null): RateLimitDecision
    {
        if ($timeoutMs !== null && $timeoutMs <= 0) {
            throw new ExecutionDeadlineException('rate_limit_store');
        }
        $quotas = RateLimitQuota::normalize($quotas);
        $now = $this->clock->monotonicMs();
        $this->expire($now);
        $pending = [];
        $blocked = [];
        $retryAfterMs = 0;
        foreach ($quotas as $quota) {
            if ($now > PHP_INT_MAX - $quota->periodMs) {
                throw new ConfigurationException(new Message('ratelimiting.quota_window_end_cannot_be_represented_in_milliseconds'));
            }
            $state = $this->states[$quota->key] ?? [
                'limit' => $quota->limit, 'periodMs' => $quota->periodMs, 'count' => 0,
                'reset' => $now + $quota->periodMs,
            ];
            if ($state['limit'] !== $quota->limit || $state['periodMs'] !== $quota->periodMs) {
                throw new ConfigurationException(new Message('ratelimiting.active_quota_definition_changed'));
            }
            if ($state['count'] >= $quota->limit) {
                $blocked[] = $quota->key;
                $retryAfterMs = max($retryAfterMs, $state['reset'] - $now);
            } else {
                $state['count']++;
            }
            $pending[$quota->key] = $state;
        }
        if ($blocked !== []) {
            return new RateLimitDecision(false, $blocked, $retryAfterMs);
        }
        foreach ($pending as $key => $state) {
            $this->states[$key] = $state;
            $this->nextExpiry = $this->nextExpiry === null
                ? $state['reset']
                : min($this->nextExpiry, $state['reset']);
        }
        return new RateLimitDecision(true);
    }

    /** Очистка запускается по ближайшему окончанию окна, не сканирует память на каждой попытке. */
    private function expire(int $now): void
    {
        if ($this->nextExpiry === null || $now < $this->nextExpiry) {
            return;
        }
        $this->nextExpiry = null;
        foreach ($this->states as $key => $state) {
            if ($state['reset'] <= $now) {
                unset($this->states[$key]);
            } else {
                $this->nextExpiry = $this->nextExpiry === null
                    ? $state['reset']
                    : min($this->nextExpiry, $state['reset']);
            }
        }
    }
}
