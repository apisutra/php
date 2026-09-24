<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Discovery;

/**
 * Режим кеширования auto-discovery.
 *
 * Используется, чтобы контролировать нагрузку на сканирование классов
 * и управлять детерминированностью поведения в разных окружениях.
 */
enum DiscoveryCacheMode: string
{
    /**
     * Включать кеш в prod/staging, выключать в dev/test.
     */
    case Auto = 'auto';
    /**
     * Принудительно включить кеш независимо от окружения.
     */
    case ForceOn = 'force_on';
    /**
     * Принудительно выключить кеш независимо от окружения.
     */
    case ForceOff = 'force_off';
}
