<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Resolver;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

final class FixedCache implements CacheInterface
{
    public function __construct(
        private array $value,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->value;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return true;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[(string) $key] = $this->get((string) $key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return true;
    }
}
