<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\Nested;

final readonly class AmbiguousObjectDto
{
    public function __construct(#[Nested] public AddressDto|ArrayFieldDto $address)
    {
    }
}
