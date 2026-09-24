<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Casts;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;

final class UppercaseCast implements CastInterface
{
    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        return is_string($value) ? strtoupper($value) : $value;
    }

    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        return is_string($value) ? strtoupper($value) : $value;
    }
}
