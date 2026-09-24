<?php

declare(strict_types=1);

namespace ApiSutra\RateLimiting\Cooldown;

interface CooldownBackendInterface
{
    /** Остаток запрета в миллисекундах; отсутствие и истечение означают 0. */
    public function remainingMs(string $key, ?int $timeoutMs = null): int;

    /** Атомарно сохраняет максимум прежнего и нового положительного остатка. */
    public function extend(string $key, int $delayMs, ?int $timeoutMs = null): CooldownUpdate;
}
