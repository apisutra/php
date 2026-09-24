<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderB\Requests;

use ApiSutra\Attributes\Response\Download;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Core\AbstractRequest;

#[Get('/provider-b/download/{operationId}')]
#[Download]
final class ProviderBDownloadRequest extends AbstractRequest
{
    public function __construct(
        #[Path('operationId')]
        public string $operationId,
    ) {}
}
