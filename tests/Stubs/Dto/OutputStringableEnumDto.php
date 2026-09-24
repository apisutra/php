<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Enums\StringableTitleStatus;

final readonly class OutputStringableEnumDto extends AbstractDto
{
    public function __construct(
        #[To('status')]
        public StringableTitleStatus $status,
    ) {}
}
