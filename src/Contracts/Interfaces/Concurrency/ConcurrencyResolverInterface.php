<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Concurrency;

interface ConcurrencyResolverInterface
{
    /**
     * Определить уровень параллелизма
     */
    public function getConcurrency(int $pending, int $completed): int;
}
