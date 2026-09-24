<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\DataTransfer\DtoHydrate;

#[DtoHydrate]
final readonly class HydratePolicyDto
{
    public function __construct(public int $id)
    {
    }
}
