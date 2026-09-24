<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class NestedItemDto extends AbstractDto
{
    public function __construct(
        #[From('id')]
        public int $id,
        #[From('name')]
        public string $name,
    ) {}
}
