<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Files\Requests\DownloadFile;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Download;
use ApiSutra\Core\AbstractRequest;

#[Get('/mega/serviceA/files/download')]
#[Download]
final class MegaServiceADownloadFileRequest extends AbstractRequest
{
}
