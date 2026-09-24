<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class OutputItemDto extends AbstractDto
{
    public function __construct(
        #[To('id')]
        public int $id,
        #[To('label')]
        public string $label,
    ) {}
}
