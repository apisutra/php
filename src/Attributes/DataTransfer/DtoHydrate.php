<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use Attribute;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Config\DateTimeHydrationPolicy;
use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\Serialization\DateTimeInvalidBehavior;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DtoHydrate
{
    public function __construct(
        public ?NamingStrategy $namingStrategy = null,
        public ?string $dateTimeFormat = null,
        public ?string $dateTimeDefaultTimezone = null,
        public ?bool $dateTimePreserveOffset = null,
        public ?bool $dateTimeStrictMissingTimezone = null,
        public ?bool $dateTimeStrictFormat = null,
        public ?DateTimeInvalidBehavior $dateTimeInvalidBehavior = null,
        public ?EmptyStringBehavior $emptyStringBehavior = null,
        public ?ScalarPolicy $scalars = null,
    ) {
    }

    public function toPolicy(DtoHydrationPolicy $base): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy(
            namingStrategy: $this->namingStrategy ?? $base->namingStrategy,
            dateTime: new DateTimeHydrationPolicy(
                format: $this->dateTimeFormat ?? $base->dateTime->format,
                defaultTimezone: $this->dateTimeDefaultTimezone ?? $base->dateTime->defaultTimezone,
                preserveOffset: $this->dateTimePreserveOffset ?? $base->dateTime->preserveOffset,
                strictMissingTimezone: $this->dateTimeStrictMissingTimezone ?? $base->dateTime->strictMissingTimezone,
                strictFormat: $this->dateTimeStrictFormat ?? $base->dateTime->strictFormat,
                invalidBehavior: $this->dateTimeInvalidBehavior ?? $base->dateTime->invalidBehavior,
            ),
            emptyStringBehavior: $this->emptyStringBehavior ?? $base->emptyStringBehavior,
        );
    }
}
