<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use Attribute;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\ShapeSpec;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Shape
{
    public function __construct(public ScalarType|ShapeSpec $value)
    {
    }
}
