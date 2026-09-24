<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use ApiSutra\Tests\Stubs\Dto\NestedItemDto;

final readonly class InterfaceObjectDto
{
    public function __construct(#[Nested(type: NestedItemDto::class)] public DtoInterface $item)
    {
    }
}
