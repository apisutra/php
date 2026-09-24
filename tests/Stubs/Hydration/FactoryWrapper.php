<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

final readonly class FactoryWrapper
{
    /** @param list<AddressDto> $items */
    private function __construct(public array $items)
    {
    }

    /** @param list<AddressDto> $items */
    public static function fromArray(array $items): self
    {
        return new self($items);
    }
}
