<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingSerialization;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Tests\Stubs\MappingExecution\OutputHandler;

final class MutableFields
{
    #[Cast(OutputHandler::class)]
    public bool $run = true;
    public int|string $number = 1;
}
