<?php

declare(strict_types=1);

namespace Example\HydrationRules;

final readonly class ReportDto
{
    /** @param list<EntryDto> $items @param list<int> $ids */
    public function __construct(
        public EntryDto $owner,
        public array $items,
        public array $ids,
        public ?int $count = null,
        public array $_extra = [],
    ) {
    }
}
