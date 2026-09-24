<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;

final class VirtualClock implements ClockInterface, SleeperInterface
{
    public int $milliseconds = 1000;
    public int $wallTime = 1_800_000_000;
    /** @var list<int> */
    public array $waits = [];

    public function monotonicMs(): int { return $this->milliseconds; }
    public function unixTime(): int { return $this->wallTime; }
    public function sleepMs(int $milliseconds): void
    {
        $this->waits[] = $milliseconds;
        $this->advance($milliseconds);
    }
    public function advance(int $milliseconds): void
    {
        $this->milliseconds += $milliseconds;
        $this->wallTime += intdiv($milliseconds, 1000);
    }
}
