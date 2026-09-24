<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use Attribute;
use ApiSutra\Config\DateTimeSerializationPolicy;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class DateTimeTo
{
    public function __construct(
        public ?string $format = null,
        public ?string $timezone = null,
    ) {
    }

    public function toPolicy(DateTimeSerializationPolicy $base): DateTimeSerializationPolicy
    {
        return new DateTimeSerializationPolicy(
            format: $this->format ?? $base->format,
            timezone: $this->timezone ?? $base->timezone,
        );
    }
}
