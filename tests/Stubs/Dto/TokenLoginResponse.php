<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\DataTransfer\AbstractResponseDto;

final readonly class TokenLoginResponse extends AbstractResponseDto
{
    public function __construct(public string $accessToken, public int $expiresIn = 600)
    {
    }
}
