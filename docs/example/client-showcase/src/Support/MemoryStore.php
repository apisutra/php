<?php

declare(strict_types=1);

namespace Example\ClientShowcase\Support;

use DateInterval;
use DateTimeImmutable;
use Override;
use Psr\SimpleCache\CacheInterface;

// Учебное хранилище одного процесса; приложение передаёт свой PSR-16 store.
final class MemoryStore implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires: ?int}> */
    private array $items = [];

    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->has($key) ? $this->items[$key]['value'] : $default;
    }

    #[Override]
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $expires = match (true) {
            $ttl instanceof DateInterval => (new DateTimeImmutable())->add($ttl)->getTimestamp(),
            is_int($ttl) => time() + $ttl,
            default => null,
        };
        $this->items[$key] = ['value' => $value, 'expires' => $expires];

        return true;
    }

    #[Override]
    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    #[Override]
    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    /**
     * @param iterable<string> $keys
     * @return array<string, mixed>
     */
    #[Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /** @param iterable<string, mixed> $values */
    #[Override]
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /** @param iterable<string> $keys */
    #[Override]
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    #[Override]
    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->items)) {
            return false;
        }
        $expires = $this->items[$key]['expires'];
        if ($expires !== null && $expires <= time()) {
            $this->delete($key);

            return false;
        }

        return true;
    }
}
