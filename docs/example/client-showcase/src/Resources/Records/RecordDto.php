<?php

declare(strict_types=1);

namespace Example\ClientShowcase\Resources\Records;

use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Attributes\DataTransfer\Extras;

// Общая модель чтения и сохранения записи в этом вымышленном API.
final readonly class RecordDto extends AbstractDto
{
    /** @param array<string, mixed> $_extra */
    public function __construct(
        public int $id,
        public string $title,
        #[Extras]
        public array $_extra = [],
    ) {
    }
}
