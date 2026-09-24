<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHydration;

final class Marker
{
    public function __construct()
    {
        Trace::$events[] = 'constructor-default';
    }
}
