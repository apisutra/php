<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\NullableShape;
use ApiSutra\Serialization\Shapes\DtoShape;

final class Recursive
{
    public function __construct(#[Shape(new NullableShape(new DtoShape(self::class)))] public ?self $child = null)
    {
    }
}
