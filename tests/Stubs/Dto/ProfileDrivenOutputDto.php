<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\Tests\Stubs\Enums\TitleStatus;

final readonly class ProfileDrivenOutputDto extends ProfileDrivenBaseDto
{
    public function __construct(
        #[To('status')]
        public TitleStatus $status,
        public ?string $plainValue = null,
    ) {}
}
