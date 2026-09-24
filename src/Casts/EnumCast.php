<?php

declare(strict_types=1);

namespace ApiSutra\Casts;

use ApiSutra\Localization\Message;
use BackedEnum;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use Override;
use TypeError;
use UnitEnum;

final class EnumCast implements CastInterface
{
    public function __construct(
        private readonly ?string $enumClass = null,
    ) {
    }

    #[Override]
    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->enumClass !== null && enum_exists($this->enumClass)) {
            $class = $this->enumClass;
            if (!is_subclass_of($class, BackedEnum::class)) {
                throw new ConfigurationException(new Message('casts.enumcast_hydrate_requires_a_backed_enum'));
            }
            try {
                return $class::tryFrom($value);
            } catch (TypeError $exception) {
                throw HydrationException::invalidValue('invalid_field_type', $class, get_debug_type($value), previous: $exception);
            }
        }

        return $value;
    }

    #[Override]
    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        if ($value instanceof UnitEnum) {
            if ($value instanceof BackedEnum) {
                return $value->value;
            }

            return $value->name;
        }

        return $value;
    }
}
