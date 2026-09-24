<?php

declare(strict_types=1);

namespace Example\Files\Resources\Files;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Response\Download;
use ApiSutra\Core\AbstractRequest;

#[Get('/files/{id}')]
#[Download]
final class DownloadFileRequest extends AbstractRequest
{
    public function __construct(#[Path] public int $id)
    {
    }
}
