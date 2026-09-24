<?php

declare(strict_types=1);

namespace ApiSutra\Config;

final readonly class DateTimeSerializationPolicy
{
    public function __construct(
        public string $format = DATE_ATOM,
        public ?string $timezone = null,
    ) {
    }
}
