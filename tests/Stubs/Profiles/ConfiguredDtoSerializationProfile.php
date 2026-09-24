<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Profiles;

use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;

final readonly class ConfiguredDtoSerializationProfile implements DtoSerializationProfileInterface
{
    public function __construct(
        private ?DtoSerializationPolicy $policy = null,
    ) {}

    public function policy(): DtoSerializationPolicy
    {
        return $this->policy ?? new DtoSerializationPolicy();
    }

    public function casts(): array
    {
        return [];
    }
}
