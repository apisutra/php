<?php

declare(strict_types=1);

namespace ApiSutra\Casts;

use ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;

final class FloatCast implements CastInterface
{
    #[\Override]
    public function hydrate(mixed $value, HydrationContext $context): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    #[\Override]
    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        return $value === null ? null : (float) $value;
    }
}
