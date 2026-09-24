<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Enums\BadTitleStatus;

final readonly class OutputBadTitleEnumDto extends AbstractDto
{
    public function __construct(
        #[To('status')]
        public BadTitleStatus $status,
    ) {}
}
