<?php

declare(strict_types=1);

namespace ApiSutra\RateLimiting\Cooldown;

final readonly class CooldownUpdate
{
    public function __construct(public bool $extended, public int $remainingMs)
    {
    }
}
