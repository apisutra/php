<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHydration;

use WeakReference;
use RuntimeException;

final class Argument
{
    public int $calls = 0;

    public function __construct()
    {
        Trace::$events[] = 'argument';
        if (Trace::$fail) {
            throw new RuntimeException('argument failed');
        }
        Trace::$arguments[] = WeakReference::create($this);
    }
}
