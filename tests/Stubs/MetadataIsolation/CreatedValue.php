<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use RuntimeException;

final class CreatedValue
{
    public static int $created = 0;
    public static bool $fail = false;

    public function __construct(public int $value = 0)
    {
        self::$created++;
        if (self::$fail) {
            throw new RuntimeException('synthetic default failure');
        }
    }
}
