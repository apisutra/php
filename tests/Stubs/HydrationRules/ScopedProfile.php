<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;

final readonly class ScopedProfile implements DtoHydrationProfileInterface
{
    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy();
    }

    public function casts(): array
    {
        return [RecordDto::class => ScopedChildCast::class];
    }
}
