<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\JsonContainerShapes;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\DtoShape;
use ApiSutra\Serialization\Shapes\NullableShape;

final readonly class Node
{
    public function __construct(
        public ?string $name = null,
        #[Shape(new NullableShape(new DtoShape(self::class)))] public ?self $child = null,
    ) {
    }
}
