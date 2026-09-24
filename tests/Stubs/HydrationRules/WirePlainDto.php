<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use DateTimeImmutable;
use stdClass;

final readonly class WirePlainDto
{
    public function __construct(
        public int $id = 7,
        public array $extra = [],
        public DateTimeImmutable $time = new DateTimeImmutable('2024-01-02T03:04:05+00:00'),
        public BackedState $status = BackedState::Ready,
        public stdClass $empty = new stdClass(),
        public array $nested = ['empty' => [], 'zero' => 0],
    ) {
        WireCounter::$constructed++;
    }
}
