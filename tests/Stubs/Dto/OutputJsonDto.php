<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class OutputJsonDto extends AbstractDto
{
    public function __construct(
        #[To('payload')]
        public JsonValue $payload,
    ) {}
}
