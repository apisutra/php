<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get\Dto;

use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class TagDto extends AbstractDto
{
    /** @param array<string, mixed> $_extra */
    public function __construct(
        public int $id,
        #[Map('label')]
        public string $name,
        #[Extras]
        public array $_extra = [],
    ) {
    }
}
