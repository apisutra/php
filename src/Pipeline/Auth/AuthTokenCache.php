<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Auth;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Timing\SystemClock;
use DateInterval;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use stdClass;

/** Изолированный вид token store; без backend хранит данные только внутри клиента. */
final class AuthTokenCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires: int|null}> */
    private array $local = [];

    public function __construct(
        private readonly ?CacheInterface $store,
        private readonly string $scope,
        private readonly ClockInterface $clock = new SystemClock(),
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->physicalKey($key);
        if ($this->store !== null) {
            return $this->store->get($key, $default);
        }
        $entry = $this->local[$key] ?? null;
        if ($entry === null) {
            return $default;
        }
        if ($entry['expires'] !== null && $entry['expires'] <= $this->clock->unixTime()) {
            unset($this->local[$key]);
            return $default;
        }
        return $entry['value'];
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $key = $this->physicalKey($key);
        if ($this->store !== null) {
            return $this->store->set($key, $value, $ttl);
        }
        $now = $this->clock->unixTime();
        $expires = $ttl instanceof DateInterval
            ? (new DateTimeImmutable())->setTimestamp($now)->add($ttl)->getTimestamp()
            : ($ttl === null ? null : $now + $ttl);
        if ($expires !== null && $expires <= $now) {
            unset($this->local[$key]);
        } else {
            $this->local[$key] = ['value' => $value, 'expires' => $expires];
        }
        return true;
    }

    public function delete(string $key): bool
    {
        $key = $this->physicalKey($key);
        if ($this->store !== null) {
            return $this->store->delete($key);
        }
        unset($this->local[$key]);
        return true;
    }

    public function clear(): bool
    {
        throw new ConfigurationException(new Message('pipeline.clearing_an_entire_scoped_token_store_is_not_supported'));
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            $success = $this->set((string) $key, $value, $ttl) && $success;
        }
        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            $success = $this->delete($key) && $success;
        }
        return $success;
    }

    public function has(string $key): bool
    {
        $missing = new stdClass();
        return $this->get($key, $missing) !== $missing;
    }

    private function physicalKey(string $key): string
    {
        return hash('sha256', serialize(['apisutra-auth-v1', 'token', $this->scope, $key]));
    }
}
