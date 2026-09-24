<?php

declare(strict_types=1);

namespace Examples\Cooldown;

use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;

/** Виртуальное время только для запуска примера без реального ожидания. */
final class ExampleClock implements ClockInterface, SleeperInterface
{
    private int $now = 0;
    /** @var list<int> */
    public array $waits = [];
    public function monotonicMs(): int
    {
        return $this->now;
    }
    public function unixTime(): int
    {
        return 1_800_000_000 + intdiv($this->now, 1000);
    }
    public function sleepMs(int $milliseconds): void
    {
        $this->waits[] = $milliseconds;
        $this->now += $milliseconds;
    }
}
