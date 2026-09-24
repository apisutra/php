<?php

declare(strict_types=1);

namespace ApiSutra\Timing;

use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use Throwable;

/** Один абсолютный срок; дочернее выполнение может только сократить его. */
final readonly class ExecutionBudget
{
    public ?int $deadlineMs;
    public ClockInterface $clock;

    public function __construct(
        ClockInterface $clock,
        ?int $limitMs = null,
        ?self $parent = null,
        ?int $startedMs = null,
        ?ExecutionDeadline $deadline = null,
    ) {
        $this->clock = $parent->clock ?? $clock;
        $deadline?->assertCompatible($this->clock);
        $start = $startedMs ?? $this->clock->monotonicMs();
        if ($limitMs !== null && ($limitMs < 1 || $limitMs > PHP_INT_MAX - $start)) {
            throw new ConfigurationException(new Message('timing.invalid_execution_budget'));
        }
        $own = $limitMs === null ? null : $start + $limitMs;
        $effective = $parent?->deadlineMs === null ? $own
            : ($own === null ? $parent->deadlineMs : min($own, $parent->deadlineMs));
        $this->deadlineMs = $deadline === null ? $effective
            : ($effective === null ? $deadline->deadlineMs : min($effective, $deadline->deadlineMs));
    }

    public function remainingMs(): ?int
    {
        return $this->deadlineMs === null ? null : max(0, $this->deadlineMs - $this->clock->monotonicMs());
    }

    public function check(string $stage, ?Throwable $previous = null): void
    {
        AsyncTask::current()?->check();
        if ($this->remainingMs() === 0) {
            throw new ExecutionDeadlineException($stage, $previous);
        }
    }

    public function wait(int $milliseconds, SleeperInterface $sleeper, string $stage): void
    {
        $this->check($stage);
        $remaining = $this->remainingMs();
        if ($remaining !== null && $milliseconds >= $remaining) {
            throw new ExecutionDeadlineException($stage);
        }
        if ($milliseconds > 0) {
            $sleeper->sleepMs($milliseconds);
        }
        $this->check($stage);
    }
}
