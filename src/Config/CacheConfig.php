<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Contracts\Interfaces\Auth\AuthLockProviderInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Enums\Cache\CacheMode;
use Psr\SimpleCache\CacheInterface;

final readonly class CacheConfig
{
    public function __construct(
        public int $ttl = 3600,
        public string $prefix = '',
        public CacheMode $mode = CacheMode::Enabled,
        public ?CacheIdentityProviderInterface $identity = null,
        public ?AuthLockProviderInterface $locks = null,
        public ?CacheInterface $store = null,
    ) {
    }

    /** Создать копию, заменяя только явно переданные поля. */
    public function with(mixed ...$overrides): self
    {
        $data = [
            'ttl' => $this->ttl,
            'prefix' => $this->prefix,
            'mode' => $this->mode,
            'identity' => $this->identity,
            'locks' => $this->locks,
            'store' => $this->store,
        ];

        foreach ($overrides as $key => $value) {
            $data[$key] = $value;
        }

        return new self(...$data);
    }
}
