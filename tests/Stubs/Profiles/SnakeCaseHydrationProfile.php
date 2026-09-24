<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Profiles;

use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;
use ApiSutra\Enums\Configuration\NamingStrategy;

final readonly class SnakeCaseHydrationProfile implements DtoHydrationProfileInterface
{
    #[\Override]
    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy(
            namingStrategy: NamingStrategy::SnakeCase,
        );
    }

    public function casts(): array
    {
        return [];
    }
}
