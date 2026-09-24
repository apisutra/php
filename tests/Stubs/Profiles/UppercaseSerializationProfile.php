<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Profiles;

use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;
use ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class UppercaseSerializationProfile implements DtoSerializationProfileInterface
{
    #[\Override]
    public function policy(): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy();
    }

    public function casts(): array
    {
        return [
            'string' => new UppercaseCast(),
        ];
    }
}
