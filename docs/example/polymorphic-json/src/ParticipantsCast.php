<?php

declare(strict_types=1);

namespace Example\PolymorphicJson;

use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Context\HydrationContext;
use Override;

final class ParticipantsCast implements HydrationCastInterface
{
    #[Override]
    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        // InputShape проверяет JSON object до cast; здесь проверяются значения словаря.
        if (!is_array($value)) {
            throw HydrationException::invalidValue('invalid_field_type', 'dictionary', get_debug_type($value));
        }
        foreach ($value as $role) {
            if (!is_string($role)) {
                throw HydrationException::invalidValue('invalid_field_type', 'string role', get_debug_type($role));
            }
        }
        return $value;
    }
}
