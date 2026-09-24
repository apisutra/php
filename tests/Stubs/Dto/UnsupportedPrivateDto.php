<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class UnsupportedPrivateDto extends AbstractDto
{
    public function __construct(
        private string $secret,
    ) {}
}
