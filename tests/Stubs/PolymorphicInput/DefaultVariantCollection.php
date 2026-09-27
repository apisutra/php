<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Tests\Stubs\HydrationRules\RecordCollection;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;

final readonly class DefaultVariantCollection
{
    public function __construct(
        #[Nested(discriminator: 'type', map: ['record' => RecordDto::class])]
        public RecordCollection $items,
    ) {
    }
}
