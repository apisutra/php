<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class IdentifierEnvelopeDto extends AbstractDto
{
    /** @param list<SimpleResponseDto> $items */
    public function __construct(
        public ?SimpleResponseDto $item = null,
        #[Nested(type: SimpleResponseDto::class)] public array $items = [],
    ) {}
}
