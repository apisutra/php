<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHydration;

use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Serialization\Context\HydrationContext;

final readonly class Cast implements HydrationCastInterface
{
    public function __construct(private Argument $argument)
    {
        Trace::$events[] = 'cast-new';
    }

    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        Trace::$events[] = 'cast:' . ++$this->argument->calls;
        return $value + $this->argument->calls;
    }
}
