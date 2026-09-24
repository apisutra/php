<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Casts;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use UnitEnum;

final class EnumToStringCast implements CastInterface
{
    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        return $value;
    }

    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        if ($value instanceof UnitEnum) {
            return 'casted:' . $value->name;
        }

        return $value;
    }
}
