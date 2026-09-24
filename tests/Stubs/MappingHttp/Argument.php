<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHttp;

use LogicException;
use WeakReference;

final class Argument
{
    public int $calls = 0;

    public function __construct(public readonly string $name)
    {
        Trace::$events[] = 'new:' . $name;
        Trace::$arguments[] = WeakReference::create($this);
        if (Trace::$fail === $name) {
            throw new LogicException('args:' . $name);
        }
    }
}
