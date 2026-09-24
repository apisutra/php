<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\Attributes\DataTransfer\Nested;

final readonly class IncomingAttributesDto
{
    public function __construct(
        #[From('source')] public mixed $from = null,
        #[Map('source')] public mixed $map = null,
        #[Nested(type: RecordDto::class)] public mixed $nested = null,
        #[Cast(CountingCast::class)] public mixed $cast = null,
        #[DateTimeFrom] public mixed $date = null,
        #[EmptyStringAsNull] public mixed $empty = null,
        #[DefaultValue(value: 1)] public mixed $default = null,
    ) {
    }
}
