<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

final readonly class ArrayWrapper
{
    /** @param list<AddressDto> $items */
    public function __construct(public array $items)
    {
    }
}
