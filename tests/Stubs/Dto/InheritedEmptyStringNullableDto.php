<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\From;

final readonly class InheritedEmptyStringNullableDto extends InheritedBaseStatusDto
{
    #[From('name')]
    #[EmptyStringAsNull]
    public ?string $name;
}
