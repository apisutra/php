<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\ConstructorValue;

final class PromotedConstructor
{
    public function __construct(#[ConstructorValue] public string $kind)
    {
    }
}
