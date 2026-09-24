<?php

declare(strict_types=1);

namespace ApiSutra\Resolver;

use Psr\SimpleCache\CacheInterface;

/**
 * Кеш результатов discovery (namespace запросов).
 *
 * При наличии PSR‑16 хранилища использует его, иначе работает в памяти
 * процесса (полезно для тестов и локальной разработки).
 */
final class ClientDiscoveryCache
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $memory = [];

    public function __construct(
        private ?CacheInterface $store = null,
        private string $prefix = 'apisutra.discovery.',
    ) {
    }

    /**
     * Получить список namespace из кеша.
     *
     * @return array<int, string>|null
     */
    public function get(string $key): ?array
    {
        $key = $this->prefix . $key;
        if ($this->store !== null) {
            $value = $this->store->get($key);
            return is_array($value) ? $value : null;
        }

        return $this->memory[$key] ?? null;
    }

    /**
     * Сохранить список namespace в кеш.
     *
     * @param array<int, string> $namespaces
     */
    public function put(string $key, array $namespaces, ?int $ttl = null): void
    {
        $key = $this->prefix . $key;
        if ($this->store !== null) {
            $this->store->set($key, $namespaces, $ttl);
            return;
        }

        $this->memory[$key] = $namespaces;
    }
}
