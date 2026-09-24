<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Catalog\Resources\Files\Requests\DownloadAttachment;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Download;
use ApiSutra\Core\AbstractRequest;

#[Get('/catalog/files/attachment')]
#[Download]
final class CatalogDownloadAttachmentRequest extends AbstractRequest
{
}
