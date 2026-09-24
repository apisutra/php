<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\DtoSerializationProfile;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Profiles\UppercaseSerializationProfile;

#[DtoSerializationProfile(UppercaseSerializationProfile::class)]
final readonly class OutputRegistryCastDto extends AbstractDto
{
    public function __construct(
        #[To('code')]
        public string $code,
    ) {}
}
