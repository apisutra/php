<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\Attributes\DataTransfer\Extras;

final readonly class SellerDto
{
    /** @param array<string, mixed> $_extra */
    public function __construct(
        public int $id,
        public string $name,
        #[Extras]
        public array $_extra = [],
    ) {
    }
}
