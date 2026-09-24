<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\DtoHydrate;
use ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Profiles\DateTimeHydrationTestProfile;
use DateTimeImmutable;

#[DtoHydrationProfile(DateTimeHydrationTestProfile::class)]
#[DtoHydrate(
    dateTimeDefaultTimezone: 'UTC',
    dateTimePreserveOffset: false,
)]
final readonly class DateTimeUtcDto extends AbstractDto
{
    public function __construct(
        #[From('created_at')]
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
