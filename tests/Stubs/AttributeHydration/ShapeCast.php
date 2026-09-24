<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Casts\IntegerCast;

final class ShapeCast
{
    #[Shape(ScalarType::Int)] #[Cast(IntegerCast::class)] public int $id = 0;
}
