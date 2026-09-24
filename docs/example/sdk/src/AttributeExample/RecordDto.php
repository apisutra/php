<?php

declare(strict_types=1);

namespace Example\Records\AttributeExample;

use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\DataTransfer\AbstractResponseDto;

final readonly class RecordDto extends AbstractResponseDto
{
    public function __construct(
        #[From('record_id')]
        public int $id,
        public string $title,
    ) {
    }
}
