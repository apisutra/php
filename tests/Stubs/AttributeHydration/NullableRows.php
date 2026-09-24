<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\ListShape;
use ApiSutra\Serialization\Shapes\NullableShape;

final readonly class NullableRows
{
    public function __construct(
        #[Shape(new NullableShape(new ListShape(new NullableShape(ScalarType::Int))))]
        public ?array $values,
    ) {
    }
}
