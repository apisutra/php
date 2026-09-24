<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Collections\OutputItemCollection;

final readonly class CollectionAutoDefaultDto extends AbstractDto
{
    public function __construct(
        #[Nested(type: OutputItemDto::class, from: 'items')]
        public OutputItemCollection $items,
    ) {}
}
