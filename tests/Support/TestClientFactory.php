<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

final class TestClientFactory
{
    /**
     * @param array<string, mixed> $overrides
     */
    public static function make(array $responses = [], array $overrides = []): TestClient
    {
        $cacheConfig = array_key_exists('cacheConfig', $overrides)
            ? $overrides['cacheConfig'] : new CacheConfig(ttl: 60, prefix: 'tests', store: new ArrayCache());
        $rateLimit = $overrides['rateLimit'] ?? new RateLimitConfig(
            limit: 5,
            period: 60,
            behavior: RateLimitBehavior::Wait,
            store: $cacheConfig?->store,
        );

        $config = new ClientConfig(
            baseUrl: (string) ($overrides['baseUrl'] ?? 'https://provider.test'),
            cacheConfig: $cacheConfig,
            rateLimit: $rateLimit,
            environment: $overrides['environment'] ?? Environment::Testing,
        );

        if ($overrides !== []) {
            $config = $config->with(...$overrides);
        }

        $transport = new MockTransport();
        if ($responses !== []) {
            $transport->fake($responses);
        }

        return new TestClient($config, $transport);
    }
}
