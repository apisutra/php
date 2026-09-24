<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Shapes;

use ApiSutra\Serialization\Rules\ScalarType;

final readonly class NullableShape implements ShapeSpec
{
    public function __construct(public ScalarType|ShapeSpec $value)
    {
    }
}
