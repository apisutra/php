<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\DtoShape;

final class MissingChild
{
    public function __construct(#[Shape(new DtoShape("MissingAttributeChild"))] public object $child)
    {
    }
}
