<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\JsonContainerShapes;

use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Execution\Async\AsyncRuntime;
use ApiSutra\Serialization\Context\HydrationContext;

final readonly class YieldingCast implements HydrationCastInterface
{
    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        new AsyncRuntime()->sleep(1);
        return $value;
    }
}
