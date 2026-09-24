<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\Nested;

final readonly class InvalidObjectTypeDto
{
    public function __construct(#[Nested(type: ArrayFieldDto::class)] public AddressDto $address)
    {
    }
}
