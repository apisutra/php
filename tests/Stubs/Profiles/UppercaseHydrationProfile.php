<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Profiles;

use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;
use ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class UppercaseHydrationProfile implements DtoHydrationProfileInterface
{
    #[\Override]
    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy();
    }

    public function casts(): array
    {
        return [
            'string' => new UppercaseCast(),
        ];
    }
}
