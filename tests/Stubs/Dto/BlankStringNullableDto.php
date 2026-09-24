<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class BlankStringNullableDto extends AbstractDto
{
    public function __construct(
        #[From('name')]
        #[EmptyStringAsNull(blank: true)]
        public ?string $name = null,
    ) {}
}
