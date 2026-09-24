<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RateLimit;

use ApiSutra\RateLimiting\Cooldown\CooldownBackendInterface;
use ApiSutra\RateLimiting\Cooldown\CooldownUpdate;
use Closure;

final class ScriptedCooldownBackend implements CooldownBackendInterface
{
    /** @var list<array{operation: string, key: string, timeout: ?int, delay: ?int}> */
    public array $calls = [];

    /**
     * @param Closure(string, ?int, int): int $read
     * @param Closure(string, int, ?int): CooldownUpdate|null $publish
     */
    public function __construct(private Closure $read, private ?Closure $publish = null)
    {
    }

    public function remainingMs(string $key, ?int $timeoutMs = null): int
    {
        $this->calls[] = ['operation' => 'read', 'key' => $key, 'timeout' => $timeoutMs, 'delay' => null];
        return ($this->read)($key, $timeoutMs, count($this->calls));
    }

    public function extend(string $key, int $delayMs, ?int $timeoutMs = null): CooldownUpdate
    {
        $this->calls[] = ['operation' => 'publish', 'key' => $key, 'timeout' => $timeoutMs, 'delay' => $delayMs];
        return $this->publish === null ? new CooldownUpdate(true, $delayMs) : ($this->publish)($key, $delayMs, $timeoutMs);
    }
}
