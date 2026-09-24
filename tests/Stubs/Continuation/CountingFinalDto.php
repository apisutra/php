<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Continuation;

use ApiSutra\Attributes\DataTransfer\From;

final class CountingFinalDto
{
    public static int $created = 0;

    public function __construct(#[From('value')] public readonly string $renamed)
    {
        self::$created++;
    }
}
