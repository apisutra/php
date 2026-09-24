<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\Serialization\EnumOutput;

final readonly class DtoSerializationPolicy
{
    public EnumOutput $enumOutput;
    public bool $strictEnums;
    public NamingStrategy $namingStrategy;
    public bool $serializeNulls;
    public DateTimeSerializationPolicy $dateTime;

    public function __construct(
        EnumOutput $enumOutput = EnumOutput::Value,
        bool $strictEnums = false,
        NamingStrategy $namingStrategy = NamingStrategy::None,
        bool $serializeNulls = false,
        ?DateTimeSerializationPolicy $dateTime = null,
    ) {
        $this->enumOutput = $enumOutput;
        $this->strictEnums = $strictEnums;
        $this->namingStrategy = $namingStrategy;
        $this->serializeNulls = $serializeNulls;
        $this->dateTime = $dateTime ?? new DateTimeSerializationPolicy();
    }

    public function merge(self $override): self
    {
        return new self(
            enumOutput: $override->enumOutput,
            strictEnums: $override->strictEnums,
            namingStrategy: $override->namingStrategy,
            serializeNulls: $override->serializeNulls,
            dateTime: $override->dateTime,
        );
    }
}
