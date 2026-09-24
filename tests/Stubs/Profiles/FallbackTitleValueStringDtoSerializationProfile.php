<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Profiles;

use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\Serialization\EnumOutput;

final readonly class FallbackTitleValueStringDtoSerializationProfile implements DtoSerializationProfileInterface
{
    public function policy(): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy(
            enumOutput: EnumOutput::TitleValueString,
            strictEnums: false,
            namingStrategy: NamingStrategy::SnakeCase,
            serializeNulls: false,
        );
    }

    public function casts(): array
    {
        return [];
    }
}
