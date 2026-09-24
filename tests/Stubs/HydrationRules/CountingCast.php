<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;

final class CountingCast implements CastInterface
{
    private int $count = 0;

    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        return ++$this->count;
    }

    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        return ++$this->count;
    }
}
