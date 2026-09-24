<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderB\Dto;

use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class ProviderBEventsResponseDto extends AbstractDto
{
    /**
     * @param array<int, array<string, mixed>> $events
     */
    public function __construct(
        #[From('events')]
        public array $events = [],
    ) {}
}
