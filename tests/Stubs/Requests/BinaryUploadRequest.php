<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Request\File;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\VO\Files\FileInput;

#[Post('/upload/binary')]
final class BinaryUploadRequest extends AbstractRequest
{
    public function __construct(
        #[File(format: FileFormat::Binary)]
        public FileInput $file,
    ) {}
}
