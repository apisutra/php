<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\Nested;

final readonly class PropertyObjectDto
{
    #[Nested(type: AddressDto::class)]
    public AddressDto $address;
}
