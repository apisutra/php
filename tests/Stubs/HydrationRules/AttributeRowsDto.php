<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Attributes\DataTransfer\Cast;

final readonly class AttributeRowsDto
{
    public function __construct(
        #[Nested(type: RecordDto::class, from: 'rows', each: 'value')]
        #[Cast(ReturnCast::class, null)]
        public array $items,
        public array $extra = [],
    ) {
    }
}
