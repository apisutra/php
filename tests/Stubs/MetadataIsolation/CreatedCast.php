<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;

final readonly class CreatedCast implements CastInterface
{
    /** @param array<string, list<CreatedValue>> $values */
    public function __construct(private array $values)
    {
    }

    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        $counter = $this->values['nested'][0];

        return ++$counter->value;
    }

    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        $counter = $this->values['nested'][0];

        return ++$counter->value;
    }
}
