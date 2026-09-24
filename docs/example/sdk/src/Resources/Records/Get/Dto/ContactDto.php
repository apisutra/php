<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get\Dto;

use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\DtoSerialize;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\DataTransfer\AbstractDto;

// Политика вывода задаётся для самого DTO; null телефона должен остаться в массиве.
#[DtoSerialize(serializeNulls: true)]
final readonly class ContactDto extends AbstractDto
{
    /** @param array<string, mixed> $_extra */
    public function __construct(
        public string $email,
        #[EmptyStringAsNull(blank: true)]
        public ?string $phone = null,
        public string $locale = 'ru',
        #[Extras]
        public array $_extra = [],
    ) {
    }
}
