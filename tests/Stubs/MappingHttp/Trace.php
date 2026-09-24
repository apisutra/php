<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHttp;

use WeakReference;

final class Trace
{
    /** @var list<string> */
    public static array $events = [];
    /** @var list<WeakReference<Argument>> */
    public static array $arguments = [];
    public static ?string $fail = null;
}
