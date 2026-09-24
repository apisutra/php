<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use DateInterval;
use InvalidArgumentException;
use Override;

/** Store проверяет переносимый формат ключей, не раскрывая их содержимое. */
final class StrictCache extends ArrayCache
{
    /** @var list<string> */
    public array $keys = [];
    public bool $failWrites = false;

    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        return parent::get($key, $default);
    }

    #[Override]
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->validateKey($key);
        $this->keys[] = $key;
        return !$this->failWrites && parent::set($key, $value, $ttl);
    }

    private function validateKey(string $key): void
    {
        if (preg_match('/^[A-Za-z0-9_.]{1,64}$/D', $key) !== 1) {
            throw new InvalidArgumentException('Недопустимый ключ тестового store');
        }
    }
}
