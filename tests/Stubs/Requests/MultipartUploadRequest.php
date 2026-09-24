<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\VO\Files\FileInput;

#[Post('/upload/multipart')]
final class MultipartUploadRequest extends AbstractRequest
{
    /**
     * @param array<int, FileInput> $files Массив файлов
     */
    public function __construct(
        #[File(name: 'files', format: FileFormat::Multipart)]
        public array $files,
        #[Body]
        public string $comment,
    ) {}
}
