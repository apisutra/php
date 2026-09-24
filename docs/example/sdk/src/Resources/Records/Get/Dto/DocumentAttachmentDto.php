<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get\Dto;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\Casts\DataUriBase64FileCast;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\VO\Files\Base64File;

final readonly class DocumentAttachmentDto extends AbstractDto
{
    #[ConstructorValue]
    public string $type;

    /** @param array<string, mixed> $_extra */
    public function __construct(
        public string $url,
        public int $pages,
        // Маленькое текстовое превью внутри JSON; это не потоковая загрузка.
        #[Map('preview_file')]
        #[Cast(DataUriBase64FileCast::class)]
        public Base64File $preview,
        #[Extras]
        public array $_extra = [],
    ) {
        $this->type = 'document';
    }
}
