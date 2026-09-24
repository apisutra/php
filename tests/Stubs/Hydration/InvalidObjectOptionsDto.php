<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\Nested;

final readonly class InvalidObjectOptionsDto
{
    public function __construct(#[Nested(each: 'value')] public AddressDto $address)
    {
    }
}
