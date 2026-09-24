<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Tests\Stubs\Collections\OutputItemCollection;

final readonly class CollectionNullDefaultDto extends AbstractDto
{
    public function __construct(
        #[DefaultValue(value: [], when: [ValueState::Null])]
        #[Nested(type: OutputItemDto::class, from: 'items')]
        public OutputItemCollection $items,
    ) {}
}
