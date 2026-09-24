<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;

final readonly class ReturnCast implements CastInterface
{
    public function __construct(private mixed $result)
    {
    }

    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        return $this->result;
    }

    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        return $this->result;
    }
}
