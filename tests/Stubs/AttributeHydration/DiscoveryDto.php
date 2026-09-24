<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use ApiSutra\Attributes\DataTransfer\Extras;

#[DtoHydrationProfile(DiscoveryProfile::class)]
final readonly class DiscoveryDto
{
    public function __construct(public int $id = 7, #[Extras] public array $_extra = [])
    {
    }
}
