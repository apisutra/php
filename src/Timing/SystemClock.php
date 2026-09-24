<?php

declare(strict_types=1);

namespace ApiSutra\Timing;

use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;

final readonly class SystemClock implements ClockInterface
{
    public function monotonicMs(): int
    {
        return (int) (hrtime(true) / 1_000_000);
    }

    public function unixTime(): int
    {
        return time();
    }
}
