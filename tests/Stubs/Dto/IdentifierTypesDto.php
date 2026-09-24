<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class IdentifierTypesDto extends AbstractDto
{
    public function __construct(
        public string $stringId,
        public int|string $unionId,
        public mixed $rawId,
        public ?int $nullableId = null,
        public float $approximate = 0.0,
    ) {}
}
