<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\JsonContainerShapes;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\ListShape;

final readonly class InterleavedDto
{
    public function __construct(
        #[Cast(YieldingCast::class)] public int $gate,
        #[Shape(new ListShape(ScalarType::String))] public array $items,
    ) {
    }
}
