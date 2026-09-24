<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\DtoSerialize;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\Enums\Serialization\EnumOutput;
use ApiSutra\Tests\Stubs\Enums\TitleStatus;

#[DtoSerialize(enumOutput: EnumOutput::Object, serializeNulls: false)]
final readonly class ProfileDrivenOverrideDto extends ProfileDrivenBaseDto
{
    public function __construct(
        #[To('status')]
        public TitleStatus $status,
        public ?string $plainValue = null,
    ) {}
}
