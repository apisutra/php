<?php

declare(strict_types=1);

namespace ApiSutra\VO\Cache;

use ApiSutra\Enums\Cache\CacheMode;

readonly class CacheOverride
{
    public function __construct(
        public ?CacheMode $mode = null,
        public ?int $ttl = null,
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }
}
