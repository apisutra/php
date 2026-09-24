<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingExecution;

use ApiSutra\Attributes\DataTransfer\DtoSerializationProfile;
use ApiSutra\Attributes\DataTransfer\RequiredInput;
use ApiSutra\Attributes\DataTransfer\Extras;

#[DtoSerializationProfile(OutputProfile::class)]
final readonly class ProfileDto
{
    public function __construct(#[RequiredInput] public int $id, #[Extras] public array $_extra = [])
    {
    }
}
