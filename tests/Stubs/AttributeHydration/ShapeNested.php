<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\ListShape;

final class ShapeNested
{
    #[Shape(new ListShape(ScalarType::Int))] #[Nested(type: Row::class)] public array $rows = [];
}
