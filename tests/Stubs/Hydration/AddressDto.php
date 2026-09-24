<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Tests\Support\HydrationConstructionProbe;

final readonly class AddressDto
{
    public function __construct(public string $city)
    {
        HydrationConstructionProbe::$calls++;
    }
}
