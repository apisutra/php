<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get\Dto;

use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class ImageAttachmentDto extends AbstractDto
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
