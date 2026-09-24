<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final readonly class ReportDto
{
    /**
     * @param list<RecordDto> $items
     * @param list<int> $ids
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public int $id,
        public OwnerDto $owner,
        public array $items,
        public array $ids,
        public ?int $count = null,
        public array $extra = [],
    ) {
    }
}
