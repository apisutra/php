<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Enums\DataTransfer\ValueState;

final readonly class EmptyStringDefaultValueDto extends AbstractDto
{
    public function __construct(
        #[From('name')]
        #[EmptyStringAsNull]
        #[DefaultValue(value: 'unknown', when: [ValueState::Null])]
        public string $name = 'initial',
    ) {}
}
