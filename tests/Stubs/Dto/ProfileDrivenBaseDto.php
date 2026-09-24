<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\DtoSerializationProfile;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Profiles\ProfileDrivenDtoSerializationProfile;

#[DtoSerializationProfile(ProfileDrivenDtoSerializationProfile::class)]
abstract readonly class ProfileDrivenBaseDto extends AbstractDto {}
