<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\Nested;

final readonly class MappedObjectDto
{
    public function __construct(
        #[From('unused')]
        #[Nested(from: 'profile.address', fallback: ['legacy.address'])]
        public ?AddressDto $address = null,
    ) {
    }
}
