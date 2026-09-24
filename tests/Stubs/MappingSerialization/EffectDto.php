<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingSerialization;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\MappingExecution\OutputHandler;

final readonly class EffectDto extends AbstractDto
{
    public function __construct(#[Cast(OutputHandler::class)] public bool $run = true)
    {
    }
}
