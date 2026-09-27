<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Tests\Stubs\JsonContainerShapes\Node;

final readonly class TypedValueDto
{
    public function __construct(public Node $value)
    {
    }
}
