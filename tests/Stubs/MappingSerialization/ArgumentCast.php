<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingSerialization;

use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;
use ApiSutra\Serialization\Context\SerializationContext;

final readonly class ArgumentCast implements SerializationCastInterface
{
    public function __construct(private Argument $argument)
    {
    }

    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        return ++$this->argument->count;
    }
}
