<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Profiles;

use ApiSutra\Config\DateTimeHydrationPolicy;
use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;

final readonly class DateTimeHydrationTestProfile implements DtoHydrationProfileInterface
{
    #[\Override]
    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy(
            dateTime: new DateTimeHydrationPolicy(
                defaultTimezone: 'Europe/Moscow',
                preserveOffset: true,
            ),
        );
    }

    public function casts(): array
    {
        return [];
    }
}
