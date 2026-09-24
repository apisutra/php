<?php

declare(strict_types=1);

namespace Example\Files\Resources\Files;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\VO\Files\FileInput;

#[Post('/files')]
final class MultipartUploadRequest extends AbstractRequest
{
    public function __construct(
        #[File('document', format: FileFormat::Multipart)]
        public FileInput $document,
        #[Body('description')]
        public string $description,
    ) {
    }
}
