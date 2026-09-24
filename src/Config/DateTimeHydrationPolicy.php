<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Enums\Serialization\DateTimeInvalidBehavior;

final readonly class DateTimeHydrationPolicy
{
    public function __construct(
        public string $format = DATE_ATOM,
        public ?string $defaultTimezone = 'UTC',
        public bool $preserveOffset = true,
        public bool $strictMissingTimezone = false,
        public bool $strictFormat = false,
        public DateTimeInvalidBehavior $invalidBehavior = DateTimeInvalidBehavior::Throw,
    ) {
    }
}
