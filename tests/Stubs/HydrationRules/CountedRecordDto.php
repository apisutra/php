<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final class CountedRecordDto
{
    public static int $constructed = 0;

    public function __construct(public readonly int $id)
    {
        self::$constructed++;
    }
}
