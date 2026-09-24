<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\RateLimiting\RateLimitBackendInterface;
use ApiSutra\RateLimiting\RateLimitDecision;
use ApiSutra\RateLimiting\RateLimitQuota;
use Closure;

final class ScriptedRateLimitBackend implements RateLimitBackendInterface
{
    public int $calls = 0;
    /** @var list<list<RateLimitQuota>> */
    public array $sets = [];
    /** @var list<int|null> */
    public array $timeouts = [];

    public function __construct(private readonly ?Closure $callback = null)
    {
    }

    public function tryAcquire(array $quotas, ?int $timeoutMs = null): RateLimitDecision
    {
        $this->calls++;
        $this->sets[] = $quotas;
        $this->timeouts[] = $timeoutMs;
        return $this->callback === null ? new RateLimitDecision(true) : ($this->callback)($quotas, $timeoutMs, $this->calls);
    }
}
