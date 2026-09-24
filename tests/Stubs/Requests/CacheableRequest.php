<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Behavior\Cache;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;

#[Get('/cacheable')]
#[Cache(ttl: 120)]
final class CacheableRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $payload,
    ) {}
}
