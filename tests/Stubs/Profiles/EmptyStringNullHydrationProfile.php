<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Profiles;

use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;
use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;

final readonly class EmptyStringNullHydrationProfile implements DtoHydrationProfileInterface
{
    #[\Override]
    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy(
            emptyStringBehavior: EmptyStringBehavior::NullIfEmpty,
        );
    }

    public function casts(): array
    {
        return [];
    }
}
