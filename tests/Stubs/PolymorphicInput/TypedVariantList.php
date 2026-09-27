<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Attributes\DataTransfer\Nested;

final readonly class TypedVariantList
{
    public function __construct(
        #[Nested(discriminator: 'type', map: ['one' => ExternalOne::class], unknownVariant: ExternalTwo::class)]
        public ExternalCollection $items,
    ) {
    }
}
