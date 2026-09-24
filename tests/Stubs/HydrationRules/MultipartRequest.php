<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Files\FileInput;

#[Post('/wire')]
final class MultipartRequest extends AbstractRequest
{
    public function __construct(#[Body] public mixed $payload, #[File] public FileInput $file)
    {
    }
}
