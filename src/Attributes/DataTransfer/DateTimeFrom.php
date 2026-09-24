<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use Attribute;
use ApiSutra\Config\DateTimeHydrationPolicy;
use ApiSutra\Enums\Serialization\DateTimeInvalidBehavior;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class DateTimeFrom
{
    public function __construct(
        public ?string $format = null,
        public ?string $defaultTimezone = null,
        public ?bool $preserveOffset = null,
        public ?bool $strictMissingTimezone = null,
        public ?bool $strictFormat = null,
        public ?DateTimeInvalidBehavior $invalidBehavior = null,
    ) {
    }

    public function toPolicy(DateTimeHydrationPolicy $base): DateTimeHydrationPolicy
    {
        return new DateTimeHydrationPolicy(
            format: $this->format ?? $base->format,
            defaultTimezone: $this->defaultTimezone ?? $base->defaultTimezone,
            preserveOffset: $this->preserveOffset ?? $base->preserveOffset,
            strictMissingTimezone: $this->strictMissingTimezone ?? $base->strictMissingTimezone,
            strictFormat: $this->strictFormat ?? $base->strictFormat,
            invalidBehavior: $this->invalidBehavior ?? $base->invalidBehavior,
        );
    }
}
