<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use ApiSutra\Attributes\DataTransfer\DtoSerializationProfile;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Profiles\DateTimeHydrationTestProfile;
use ApiSutra\Tests\Stubs\Profiles\DateTimeSerializationTestProfile;
use DateTimeImmutable;

#[DtoHydrationProfile(DateTimeHydrationTestProfile::class)]
#[DtoSerializationProfile(DateTimeSerializationTestProfile::class)]
final readonly class DateTimeDto extends AbstractDto
{
    public function __construct(
        #[From('created_at')]
        #[To('created_at')]
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
