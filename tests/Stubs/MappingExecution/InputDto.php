<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingExecution;

use ApiSutra\Attributes\DataTransfer\Cast;

final readonly class InputDto
{
    public function __construct(#[Cast(InputHandler::class)] public mixed $value = null)
    {
    }
}
