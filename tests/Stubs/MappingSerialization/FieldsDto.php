<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingSerialization;

use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use ApiSutra\Attributes\DataTransfer\DtoSerializationProfile;
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;
use DateTimeImmutable;

#[DtoSerializationProfile(LiveProfile::class)]
final readonly class FieldsDto extends AbstractDto
{
    public function __construct(
        #[To('record.id'), Map('ignored')] public int|string $recordId = 7,
        public mixed $child = null,
        #[DateTimeTo(format: 'Y')] public ?DateTimeImmutable $createdAt = null,
        public ?string $optional = null,
    ) {
    }
}
