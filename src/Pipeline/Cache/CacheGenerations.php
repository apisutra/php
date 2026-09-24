<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Cache;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use Psr\SimpleCache\CacheInterface;

/**
 * Непрозрачные поколения исключают повторное использование инвалидированных записей.
 * PSR-16 не даёт атомарной инициализации: гонка может дать лишний miss, но не старый hit.
 */
final readonly class CacheGenerations
{
    public function __construct(private CacheInterface $store)
    {
    }

    public static function key(string $kind, string $scope, ?string $group = null): string
    {
        return hash('sha256', serialize(['apisutra-cache-v2', $kind, $scope, $group]));
    }

    public function current(string $key, ?int $ttl = null, bool $create = true): ?string
    {
        $value = $this->store->get($key);
        if (is_string($value) && preg_match('/^[a-f0-9]{32}$/D', $value) === 1) {
            return $value;
        }

        return $create ? $this->invalidate($key, $ttl) : null;
    }

    public function invalidate(string $key, ?int $ttl = null): string
    {
        $generation = bin2hex(random_bytes(16));
        if (!$this->store->set($key, $generation, $ttl)) {
            throw new ConfigurationException(new Message('pipeline.failed_to_save_the_cache_generation'));
        }

        return $generation;
    }
}
