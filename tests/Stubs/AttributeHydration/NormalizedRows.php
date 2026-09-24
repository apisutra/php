<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\ListShape;

final readonly class NormalizedRows
{
    public function __construct(#[Shape(new ListShape(ScalarType::Int, normalizeKeys: true))] public array $values)
    {
    }
}
