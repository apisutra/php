<?php

declare(strict_types=1);

namespace Example\Records\Config;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;

final class ClientConfigFactory
{
    public const string DEFAULT_BASE_URL = 'https://records.example.test';
    public const int DEFAULT_TIMEOUT = 30;

    public static function create(string $baseUrl = self::DEFAULT_BASE_URL): ClientConfig
    {
        return new ClientConfig(...self::defaults($baseUrl));
    }

    /** @return array{baseUrl: string, timeout: int, hydration: HydrationConfig} */
    public static function defaults(string $baseUrl = self::DEFAULT_BASE_URL): array
    {
        return [
            'baseUrl' => $baseUrl,
            'timeout' => self::DEFAULT_TIMEOUT,
            'hydration' => HydrationConfigFactory::create(),
        ];
    }
}
