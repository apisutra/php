<?php

declare(strict_types=1);

namespace ApiSutra\Casts;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Serialization\IntegerRange;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use Override;

final class IntegerCast implements CastInterface
{
    #[Override]
    public function hydrate(mixed $value, HydrationContext $context): ?int
    {
        if ($value === null) {
            return null;
        }

        if (IntegerRange::overflows($value)) {
            throw HydrationException::invalidValue('integer_out_of_range', 'int', get_debug_type($value));
        }

        return (int) $value;
    }

    #[Override]
    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        if (IntegerRange::overflows($value)) {
            throw new SerializationException(new Message('casts.integercast_value_is_outside_the_int_range_during_serialization'));
        }
        return $value === null ? null : (int) $value;
    }
}
