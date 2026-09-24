<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use DateInterval;
use DateTimeImmutable;
use Override;
use Psr\SimpleCache\CacheInterface;

class ArrayCache implements CacheInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * @var array<string, int|null>
     */
    private array $expiresAt = [];

    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->values[$key];
    }

    #[Override]
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->values[$key] = $value;
        $this->expiresAt[$key] = $this->resolveTtl($ttl);

        return true;
    }

    #[Override]
    public function delete(string $key): bool
    {
        unset($this->values[$key], $this->expiresAt[$key]);

        return true;
    }

    #[Override]
    public function clear(): bool
    {
        $this->values = [];
        $this->expiresAt = [];

        return true;
    }

    #[Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $keyString = (string) $key;
            $result[$keyString] = $this->get($keyString, $default);
        }

        return $result;
    }

    #[Override]
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    #[Override]
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    #[Override]
    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->values)) {
            return false;
        }

        $expiresAt = $this->expiresAt[$key] ?? null;
        if ($expiresAt !== null && $expiresAt < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    private function resolveTtl(DateInterval|int|null $ttl): ?int
    {
        if ($ttl instanceof DateInterval) {
            $now = new DateTimeImmutable();
            $expiresAt = $now->add($ttl)->getTimestamp();
            return $expiresAt;
        }

        if (is_int($ttl)) {
            return time() + $ttl;
        }

        return null;
    }
}
