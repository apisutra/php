<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Shapes;

final readonly class DtoShape implements ShapeSpec
{
    public function __construct(public string $class, public bool $emptyListAsObject = false)
    {
    }
}
