<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final class WireCounter
{
    public static int $constructed = 0;
    public static int $casts = 0;
}
