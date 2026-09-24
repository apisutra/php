<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Nested;

final class LegacyParent
{
    public function __construct(#[Nested(type: Row::class)] public Row $child)
    {
    }
}
