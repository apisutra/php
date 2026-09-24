<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class ScalarAutoCastDto extends AbstractDto
{
    public function __construct(
        public ?int $intValue = null,
        public ?float $floatValue = null,
        public ?bool $boolValue = null,
        public ?string $stringValue = null,
    ) {}
}
