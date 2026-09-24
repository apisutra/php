<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use DateInterval;
use Override;

final class SpyCache extends ArrayCache
{
    public ?string $lastGetKey = null;
    public ?string $lastSetKey = null;
    public DateInterval|int|null $lastSetTtl = null;
    public ?string $lastDeleteKey = null;

    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        $this->lastGetKey = $key;

        return parent::get($key, $default);
    }

    #[Override]
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->lastSetKey = $key;
        $this->lastSetTtl = $ttl;

        return parent::set($key, $value, $ttl);
    }

    #[Override]
    public function delete(string $key): bool
    {
        $this->lastDeleteKey = $key;

        return parent::delete($key);
    }
}
