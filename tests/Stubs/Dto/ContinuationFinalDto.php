<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class ContinuationFinalDto extends AbstractDto
{
    public function __construct(
        public string $value,
    ) {}
}
