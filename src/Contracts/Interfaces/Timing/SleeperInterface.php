<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Timing;

interface SleeperInterface
{
    public function sleepMs(int $milliseconds): void;
}
