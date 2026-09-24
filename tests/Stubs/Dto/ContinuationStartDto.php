<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class ContinuationStartDto extends AbstractDto
{
    public function __construct(
        public ?string $operationToken = null,
        public ?string $value = null,
    ) {}
}
