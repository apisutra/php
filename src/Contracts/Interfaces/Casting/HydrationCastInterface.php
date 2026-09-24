<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Casting;

use ApiSutra\Serialization\Context\HydrationContext;

interface HydrationCastInterface
{
    public function hydrate(mixed $value, HydrationContext $context): mixed;
}
