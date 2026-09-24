<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RelativeTypes;

use ApiSutra\Attributes\DataTransfer\Nested;

final readonly class NestedSelf
{
    public function __construct(#[Nested] public ?self $child = null)
    {
    }
}
