<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\Nested;

final readonly class ObjectDto
{
    public function __construct(#[Nested(type: AddressDto::class)] public AddressDto $address)
    {
    }
}
