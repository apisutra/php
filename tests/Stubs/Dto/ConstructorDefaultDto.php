<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class ConstructorDefaultDto extends AbstractDto
{
    public function __construct(
        #[From('middle_name')]
        public ?string $middleName = 'somename',
    ) {}
}
