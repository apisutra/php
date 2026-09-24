<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Cache;

use ApiSutra\Enums\Cache\CacheMode;
use Psr\SimpleCache\CacheInterface;

/** Снимок кеширования одного исполнения, не разделяется между запросами. */
final class CacheExecutionState
{
    public ?string $key = null;
    public ?string $requestIdentity = null;
    public bool $hit = false;

    public function __construct(
        public readonly CacheInterface $store,
        public readonly string $scopeKey,
        public readonly string $scopeGeneration,
        public readonly string $groupKey,
        public readonly string $groupGeneration,
        public readonly CacheMode $mode,
        public readonly int $ttl,
        public readonly string $scopeIdentity,
        public readonly string $tenantIdentity,
    ) {
    }
}
