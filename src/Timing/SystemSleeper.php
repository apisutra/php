<?php

declare(strict_types=1);

namespace ApiSutra\Timing;

use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;

final class SystemSleeper implements SleeperInterface
{
    public function sleepMs(int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            return;
        }

        usleep($milliseconds * 1000);
    }
}
