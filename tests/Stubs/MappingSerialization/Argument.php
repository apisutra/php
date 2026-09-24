<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingSerialization;

use RuntimeException;
use WeakReference;

final class Argument
{
    public static int $created = 0;
    public static bool $fail = false;
    /** @var WeakReference<self>|null */
    public static ?WeakReference $last = null;
    public int $count = 0;

    public function __construct()
    {
        self::$created++;
        self::$last = WeakReference::create($this);
        if (self::$fail) {
            throw new RuntimeException('argument failure');
        }
    }
}
