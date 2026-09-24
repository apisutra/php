<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RelativeTypes;

use ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use ApiSutra\Serialization\Hydrator;

class TraitNode implements DtoInterface
{
    use SelfProperties;

    public static function from(array|object $data): static
    {
        return Hydrator::default()->hydrate($data, static::class);
    }
}
