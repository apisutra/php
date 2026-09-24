<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use ApiSutra\Tests\Stubs\Profiles\SnakeCaseHydrationProfile;

#[DtoHydrationProfile(SnakeCaseHydrationProfile::class)]
final readonly class Profiled
{
    public function __construct(public int $recordId, public ?string $value = null)
    {
    }
}
