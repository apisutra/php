<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use DateInterval;

final class LockingCache extends ArrayCache
{
    public ?string $lastAddKey = null;
    public ?string $lastDeleteKey = null;

    public function add(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->lastAddKey = $key;
        if ($this->has($key)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        $this->lastDeleteKey = $key;

        return parent::delete($key);
    }
}
