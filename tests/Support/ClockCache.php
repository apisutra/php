<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use DateInterval;
use DateTimeImmutable;
use Override;

/** Наблюдаемый PSR-16 backend с управляемым временем и переносимым снимком записей. */
final class ClockCache extends ArrayCache
{
    /** @var array<string, array{value: mixed, expires: ?int}> */
    public array $entries = [];
    /** @var list<string> */
    public array $events = [];

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        $this->events[] = 'get';
        return $this->has($key) ? $this->entries[$key]['value'] : $default;
    }

    #[Override]
    public function has(string $key): bool
    {
        $this->events[] = 'has';
        $entry = $this->entries[$key] ?? null;
        return $entry !== null && ($entry['expires'] === null || $entry['expires'] > $this->clock->unixTime());
    }

    #[Override]
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->events[] = 'set';
        $now = $this->clock->unixTime();
        $expires = $ttl instanceof DateInterval
            ? (new DateTimeImmutable())->setTimestamp($now)->add($ttl)->getTimestamp()
            : ($ttl === null ? null : $now + $ttl);
        $this->entries[$key] = ['value' => $value, 'expires' => $expires];
        return true;
    }

    #[Override]
    public function delete(string $key): bool
    {
        $this->events[] = 'delete';
        unset($this->entries[$key]);
        return true;
    }

    #[Override]
    public function clear(): bool
    {
        $this->events[] = 'clear';
        $this->entries = [];
        return true;
    }
}
