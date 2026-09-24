<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;

final readonly class ValueHandler implements CastInterface, DefaultValueProviderInterface
{
    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        State::$handlers++;
        return $value;
    }

    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        return $value;
    }

    public function resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed
    {
        State::$handlers++;
        return State::$value;
    }
}
