<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;

final class FakeSleeper implements SleeperInterface
{
    public int $calls = 0;
    public int $totalMs = 0;

    public function __construct(private ?VirtualClock $clock = null)
    {
    }

    public function sleepMs(int $milliseconds): void
    {
        $this->clock?->advance($milliseconds);
        $this->calls++;
        $this->totalMs += max(0, $milliseconds);
    }
}
