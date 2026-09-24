<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Profiles\SnakeCaseHydrationProfile;

#[DtoHydrationProfile(SnakeCaseHydrationProfile::class)]
final readonly class MapBidirectionalDto extends AbstractDto
{
    public function __construct(
        #[Map('query_num')]
        public string $queryNumber,
        public string $plainValue,
    ) {}
}
