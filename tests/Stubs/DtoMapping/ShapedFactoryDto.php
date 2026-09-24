<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\DtoMapping;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\DtoShape;
use ApiSutra\Serialization\Shapes\ListShape;

final class ShapedFactoryDto
{
    /** @param list<FactoryDto> $children */
    public function __construct(#[Shape(new ListShape(new DtoShape(FactoryDto::class)))] public array $children) {}
}
