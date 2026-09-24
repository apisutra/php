<?php

declare(strict_types=1);

namespace ApiSutra\Timing;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

/** Неизменяемый общий срок нескольких вызовов в одном процессе. */
final readonly class ExecutionDeadline
{
    private function __construct(public int $deadlineMs, public ClockInterface $clock)
    {
    }

    public static function afterMs(int $milliseconds, ?ClockInterface $clock = null): self
    {
        $clock ??= new SystemClock();
        $now = $clock->monotonicMs();
        if ($milliseconds < 0 || $milliseconds > PHP_INT_MAX - $now) {
            throw new ConfigurationException(new Message('timing.invalid_external_deadline'));
        }

        return new self($now + $milliseconds, $clock);
    }

    public function assertCompatible(ClockInterface $clock): void
    {
        if ($clock !== $this->clock && !($clock instanceof SystemClock && $this->clock instanceof SystemClock)) {
            throw new ConfigurationException(new Message('timing.deadline_and_execution_must_use_compatible_monotonic_clocks'));
        }
    }
}
