<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Behavior;

use Attribute;
use ApiSutra\Enums\Cache\CacheMode;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Cache
{
    public function __construct(
        public ?int $ttl = null,
        public CacheMode $mode = CacheMode::Enabled,
        public ?string $key = null,
    ) {
    }
}
