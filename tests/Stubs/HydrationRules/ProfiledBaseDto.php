<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use ApiSutra\DataTransfer\AbstractDto;

#[DtoHydrationProfile(ScopedProfile::class)]
abstract readonly class ProfiledBaseDto extends AbstractDto
{
}
