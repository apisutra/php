<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\Nested;

final readonly class ArrayFieldObjectDto
{
    public function __construct(#[Nested(from: 'source')] public ArrayFieldDto $child)
    {
    }
}
