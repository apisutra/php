<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Profiles;

use ApiSutra\Config\DateTimeSerializationPolicy;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;

final readonly class DateTimeSerializationTestProfile implements DtoSerializationProfileInterface
{
    #[\Override]
    public function policy(): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy(
            dateTime: new DateTimeSerializationPolicy(
                format: 'Y-m-d H:i',
                timezone: 'UTC',
            ),
        );
    }

    public function casts(): array
    {
        return [];
    }
}
