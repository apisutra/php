<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\VO\Files\FileInput;

#[Post('/upload/base64')]
final class Base64UploadRequest extends AbstractRequest
{
    public function __construct(
        #[File(name: 'file', format: FileFormat::Base64)]
        public FileInput $file,
        #[Body]
        public string $note,
    ) {}
}
