<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Behavior\Cache;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Cache\CacheMode;

#[Get('/cache-disabled')]
#[Cache(ttl: 120, mode: CacheMode::Disabled)]
final class CacheDisabledRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $payload,
    ) {}
}
