<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\DtoMapping;

use ApiSutra\Attributes\DataTransfer\Nested;

final class NestedFactoryDto
{
    public function __construct(#[Nested(type: FactoryDto::class)] public FactoryDto $child) {}
}
