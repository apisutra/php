<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;

final readonly class CountingCast implements CastInterface
{
    public function __construct(private MutableCounter $counter)
    {
    }

    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        return ++$this->counter->value;
    }

    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        return ++$this->counter->value;
    }
}
