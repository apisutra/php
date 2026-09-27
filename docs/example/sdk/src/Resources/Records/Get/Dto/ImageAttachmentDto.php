<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get\Dto;

use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Attributes\DataTransfer\Extras;

final readonly class ImageAttachmentDto extends AttachmentDto
{
    #[ConstructorValue]
    public string $type;

    /** @param array<string, mixed> $_extra */
    public function __construct(
        public string $url,

        public int $width,

        public int $height,

        #[Extras]
        public array $_extra = [],
    ) {
        $this->type = 'image';
    }
}
