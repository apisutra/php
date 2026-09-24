<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use DateTimeImmutable;

final class DateReceiverDto extends DateTimeImmutable
{
    public function __construct(public array $extra = [])
    {
        parent::__construct('2026-01-01T00:00:00Z');
    }
}
