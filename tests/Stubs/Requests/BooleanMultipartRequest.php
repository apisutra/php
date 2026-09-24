<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\VO\Files\FileInput;

#[Post('/booleans/multipart')]
final class BooleanMultipartRequest extends BooleanWireRequest
{
    /** @var list<FileInput> */
    #[File(format: FileFormat::Multipart)]
    public array $files = [];
}
