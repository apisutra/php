<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Behavior\Cache;
use ApiSutra\Attributes\Response\Download;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/download/cached')]
#[Download]
#[Cache(ttl: 120)]
final class DownloadCacheRequest extends AbstractRequest
{
}
