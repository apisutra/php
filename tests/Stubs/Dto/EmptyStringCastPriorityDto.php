<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class EmptyStringCastPriorityDto extends AbstractDto
{
    public function __construct(
        #[From('name')]
        #[EmptyStringAsNull]
        #[Cast(UppercaseCast::class)]
        public string $name,
    ) {}
}
