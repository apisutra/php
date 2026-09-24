<?php

declare(strict_types=1);

namespace ApiSutra\RateLimiting\Cooldown\Backends;

use ApiSutra\RateLimiting\Cooldown\CooldownBackendInterface;
use ApiSutra\RateLimiting\Cooldown\CooldownUpdate;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Localization\Message;
use ApiSutra\Timing\SystemClock;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;

/** Только сроки активных областей; без фоновых таймеров. */
final class LocalCooldownBackend implements CooldownBackendInterface
{
    /** @var array<string, int> */
    private array $deadlines = [];
    private ?int $nextExpiry = null;

    public function __construct(private readonly ClockInterface $clock = new SystemClock())
    {
    }

    public function remainingMs(string $key, ?int $timeoutMs = null): int
    {
        $this->checkTimeout($timeoutMs, 'cooldown_read');
        if ($this->nextExpiry === null) {
            return 0;
        }
        $now = $this->clock->monotonicMs();
        $this->prune($now);
        return max(0, ($this->deadlines[$key] ?? $now) - $now);
    }

    public function extend(string $key, int $delayMs, ?int $timeoutMs = null): CooldownUpdate
    {
        $this->checkTimeout($timeoutMs, 'cooldown_publish');
        $now = $this->clock->monotonicMs();
        $this->prune($now);
        if ($delayMs <= 0 || $delayMs > PHP_INT_MAX - $now) {
            throw new ConfigurationException(new Message('rate_limit.invalid_cooldown_duration'));
        }
        $deadline = $now + $delayMs;
        $current = $this->deadlines[$key] ?? $now;
        if ($deadline <= $current) {
            return new CooldownUpdate(false, $current - $now);
        }
        $this->deadlines[$key] = $deadline;
        $this->nextExpiry = min($this->nextExpiry ?? $deadline, $deadline);
        return new CooldownUpdate(true, $delayMs);
    }

    private function checkTimeout(?int $timeoutMs, string $stage): void
    {
        if ($timeoutMs !== null && $timeoutMs <= 0) {
            throw new ExecutionDeadlineException($stage);
        }
    }

    private function prune(int $now): void
    {
        if ($this->nextExpiry === null || $now < $this->nextExpiry) {
            return;
        }
        $this->nextExpiry = null;
        foreach ($this->deadlines as $key => $deadline) {
            if ($deadline <= $now) {
                unset($this->deadlines[$key]);
            } else {
                $this->nextExpiry = min($this->nextExpiry ?? $deadline, $deadline);
            }
        }
    }
}
