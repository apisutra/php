<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get\Dto;

use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\DataTransfer\AbstractDto;
use Example\Records\Resources\Records\Get\AuthorDisplayNameProvider;

// Вложенный DTO использует те же правила гидратации и toArray(), что и корень.
final readonly class AuthorDto extends AbstractDto
{
    /** @param array<string, mixed> $_extra */
    public function __construct(
        public int $id,
        #[Map('first_name')]
        public string $firstName,
        #[Map('last_name')]
        public string $lastName,
        #[Nested(type: ContactDto::class)]
        public ContactDto $contact,
        #[Map('display_name')]
        #[DefaultValue(provider: AuthorDisplayNameProvider::class)]
        public string $displayName,
        #[Extras]
        public array $_extra = [],
    ) {
    }
}
