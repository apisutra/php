<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use DateInterval;
use Override;
use RuntimeException;

final class FailingRateLimitStore extends ArrayCache
{
    public ?string $failure = null;
    public int $reads = 0;
    public int $writes = 0;

    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        $this->reads++;
        if ($this->failure === 'read') {
            throw new RuntimeException('fixture-store-secret');
        }
        return parent::get($key, $default);
    }

    #[Override]
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->writes++;
        if ($this->failure === 'false') {
            return false;
        }
        // Запись могла состояться до потери подтверждения.
        $saved = parent::set($key, $value, $ttl);
        if ($this->failure === 'write') {
            throw new RuntimeException('fixture-store-secret');
        }
        return $saved;
    }
}
