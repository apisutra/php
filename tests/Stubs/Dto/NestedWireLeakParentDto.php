<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\DtoSerializationProfile;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Profiles\FallbackTitleValueStringDtoSerializationProfile;

#[DtoSerializationProfile(FallbackTitleValueStringDtoSerializationProfile::class)]
final readonly class NestedWireLeakParentDto extends AbstractDto
{
    public function __construct(
        public NestedWireLeakChildDto $nested,
    ) {}
}
