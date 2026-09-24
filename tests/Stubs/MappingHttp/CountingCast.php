<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHttp;

use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;
use ApiSutra\Serialization\Context\SerializationContext;

final readonly class CountingCast implements SerializationCastInterface
{
    public function __construct(private Argument $argument)
    {
    }

    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        Trace::$events[] = 'cast:' . $this->argument->name . ':' . ++$this->argument->calls;
        return $value + 10;
    }
}
