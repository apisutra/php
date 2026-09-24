<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Timing;

interface ClockInterface
{
    public function monotonicMs(): int;
    public function unixTime(): int;
}
