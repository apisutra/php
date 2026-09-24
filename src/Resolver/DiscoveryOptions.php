<?php

declare(strict_types=1);

namespace ApiSutra\Resolver;

use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Discovery\DiscoveryCacheMode;

/**
 * Настройки auto-discovery для клиента.
 *
 * - cacheMode: стратегия включения кеша.
 * - cacheTtl: TTL для кеша (null = дефолт стора).
 * - cacheKeyVersion: ручной bump версии ключа для инвалидации.
 */
readonly class DiscoveryOptions
{
    public function __construct(
        public DiscoveryCacheMode $cacheMode = DiscoveryCacheMode::Auto,
        public ?int $cacheTtl = null,
        public ?string $cacheKeyVersion = null,
    ) {
    }

    /**
     * Режим по умолчанию (адаптация под окружение).
     */
    public static function auto(): self
    {
        return new self();
    }

    /**
     * Принудительно включить кеш и опционально задать TTL.
     */
    public static function forceOn(?int $ttl = null): self
    {
        return new self(cacheMode: DiscoveryCacheMode::ForceOn, cacheTtl: $ttl);
    }

    /**
     * Принудительно выключить кеш (полный live‑scan).
     */
    public static function forceOff(): self
    {
        return new self(cacheMode: DiscoveryCacheMode::ForceOff);
    }

    /**
     * Определить, включён ли кеш для указанного окружения.
     */
    public function isCacheEnabled(Environment $environment): bool
    {
        return match ($this->cacheMode) {
            DiscoveryCacheMode::ForceOn => true,
            DiscoveryCacheMode::ForceOff => false,
            DiscoveryCacheMode::Auto => in_array($environment, [Environment::Production, Environment::Staging], true),
        };
    }
}
