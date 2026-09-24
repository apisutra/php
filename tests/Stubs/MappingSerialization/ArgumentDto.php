<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingSerialization;

use ApiSutra\Attributes\DataTransfer\Cast;

final readonly class ArgumentDto
{
    public function __construct(#[Cast(ArgumentCast::class, new Argument())] public ?int $value = null)
    {
    }
}
