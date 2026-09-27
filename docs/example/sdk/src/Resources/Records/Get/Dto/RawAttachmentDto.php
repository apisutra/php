<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get\Dto;

use ApiSutra\Attributes\DataTransfer\Extras;

final readonly class RawAttachmentDto extends AttachmentDto
{
    /** @param array<array-key, mixed> $raw */
    public function __construct(
        // Сохраняем весь неизвестный вариант, включая discriminator и значения false/null/[].
        #[Extras]
        public array $raw = [],
    ) {
    }
}
