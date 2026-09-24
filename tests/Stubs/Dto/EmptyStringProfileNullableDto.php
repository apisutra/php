<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Profiles\EmptyStringNullHydrationProfile;

#[DtoHydrationProfile(EmptyStringNullHydrationProfile::class)]
final readonly class EmptyStringProfileNullableDto extends AbstractDto
{
    public function __construct(
        #[From('name')]
        public ?string $name = null,
    ) {}
}
