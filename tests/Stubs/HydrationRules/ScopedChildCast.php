<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;

final readonly class ScopedChildCast implements CastInterface
{
    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        return $context->hydrate($value, RecordDto::class);
    }

    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        return $value;
    }
}
