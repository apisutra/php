<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Tests\Stubs\JsonContainerShapes\Node;

final readonly class CastFallbackDto
{
    /** @param list<Node|RawDto> $value */
    public function __construct(
        #[Nested(discriminator: 'type', map: ['node' => Node::class], unknownVariant: RawDto::class, itemCast: RawDtoCast::class)]
        public array $value,
    ) {
    }
}
