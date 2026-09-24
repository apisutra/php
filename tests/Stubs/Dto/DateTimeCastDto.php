<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DtoSerializationProfile;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\Casts\DateTimeCast;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Profiles\DateTimeSerializationTestProfile;
use DateTimeImmutable;

#[DtoSerializationProfile(DateTimeSerializationTestProfile::class)]
final readonly class DateTimeCastDto extends AbstractDto
{
    public function __construct(
        #[To('created_at')]
        #[Cast(DateTimeCast::class, format: 'Y-m-d')]
        public DateTimeImmutable $createdAt,
    ) {}
}
