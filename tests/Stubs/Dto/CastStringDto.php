<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Profiles\UppercaseHydrationProfile;

#[DtoHydrationProfile(UppercaseHydrationProfile::class)]
final readonly class CastStringDto extends AbstractDto
{
    public function __construct(
        public string $value,
    ) {}
}
