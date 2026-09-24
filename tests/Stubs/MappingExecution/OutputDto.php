<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingExecution;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\Extras;

final readonly class OutputDto
{
    public function __construct(
        #[Cast(OutputHandler::class)] public mixed $value = null,
        #[Extras] public array $_extra = [],
    ) {
    }
}
