<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Casts\IntegerCast;

final readonly class NestedCastPriorityDto
{
    public function __construct(
        #[From('main', fallback: ['alternative'])]
        #[Cast(IntegerCast::class)]
        #[Nested]
        public AddressDto $address,
    ) {
    }
}
