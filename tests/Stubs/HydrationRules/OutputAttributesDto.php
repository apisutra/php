<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use DateTimeImmutable;

final readonly class OutputAttributesDto
{
    public function __construct(
        #[From('source')] public int $id,
        #[To('out')] public string $label,
        #[DateTimeTo] public DateTimeImmutable $date,
        #[ForeignAttribute] public string $other = 'default',
    ) {
    }
}
