<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Request;

use Attribute;
use ApiSutra\Enums\Http\FileFormat;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class File
{
    public function __construct(
        public ?string $name = null,
        public FileFormat $format = FileFormat::Multipart,
    ) {
    }
}
