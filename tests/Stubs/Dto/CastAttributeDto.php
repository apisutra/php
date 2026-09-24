<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class CastAttributeDto extends AbstractDto
{
    public function __construct(
        #[Cast(UppercaseCast::class)]
        public string $value,
    ) {}
}
