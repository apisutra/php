<?php

declare(strict_types=1);

namespace Example\HydrationRules;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;

final readonly class EntryCast implements HydrationCastInterface
{
    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        return $context->hydrate($value, EntryDto::class);
    }

}
