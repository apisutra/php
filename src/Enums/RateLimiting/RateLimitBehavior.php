<?php

declare(strict_types=1);

namespace ApiSutra\Enums\RateLimiting;

enum RateLimitBehavior: string
{
    case Wait = 'wait';
    case Throw = 'throw';
}
