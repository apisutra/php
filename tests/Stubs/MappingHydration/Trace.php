<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHydration;

use WeakReference;

final class Trace
{
    /** @var list<string> */
    public static array $events = [];
    /** @var list<WeakReference<object>> */
    public static array $arguments = [];
    public static bool $snake = false;
    public static bool $fail = false;
}
