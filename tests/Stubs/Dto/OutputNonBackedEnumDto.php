<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Enums\NonBackedStatus;

final readonly class OutputNonBackedEnumDto extends AbstractDto
{
    public function __construct(
        #[To('status')]
        public NonBackedStatus $status,
    ) {}
}
