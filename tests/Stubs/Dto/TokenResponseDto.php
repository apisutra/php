<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\DataTransfer\AbstractResponseDto;

final readonly class TokenResponseDto extends AbstractResponseDto
{
    public function __construct(
        public string $token,
    ) {}
}
