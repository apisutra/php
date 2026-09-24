<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use ApiSutra\Attributes\DataTransfer\DtoSerializationProfile;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Profiles\DateTimeSerializationTestProfile;
use DateTimeImmutable;

#[DtoSerializationProfile(DateTimeSerializationTestProfile::class)]
final readonly class DateTimeToOverrideDto extends AbstractDto
{
    public function __construct(
        #[To('created_at')]
        #[DateTimeTo(format: DATE_ATOM)]
        public DateTimeImmutable $createdAt,
    ) {}
}
