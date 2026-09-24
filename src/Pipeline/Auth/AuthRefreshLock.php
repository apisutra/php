<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Auth;

use ApiSutra\Contracts\Interfaces\Auth\AuthLockLeaseInterface;
use ApiSutra\Contracts\Interfaces\Auth\AuthLockProviderInterface;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Timing\SystemClock;
use Psr\SimpleCache\CacheInterface;

final class AuthRefreshLock
{
    private readonly AuthLockProviderInterface $provider;

    /** @var array<string, array{key: string, lease: AuthLockLeaseInterface}> */
    private array $leases = [];

    public function __construct(
        ?CacheInterface $cache = null,
        ?AuthLockProviderInterface $locks = null,
        ?ClockInterface $clock = null,
    ) {
        $this->provider = $locks ?? ($cache instanceof AuthLockProviderInterface ? $cache : new LocalAuthLockProvider($clock ?? new SystemClock()));
    }

    public function acquireLease(string $key, int $ttlSeconds): ?AuthLockLeaseInterface
    {
        return $this->provider->acquire($key, $ttlSeconds);
    }

    /** Совместимый фасад; release использует тот же lease/backend, что и acquire. */
    public function acquire(string $key, int $ttlSeconds): ?string
    {
        $lease = $this->acquireLease($key, $ttlSeconds);
        if ($lease === null) {
            return null;
        }
        $token = bin2hex(random_bytes(16));
        $this->leases[$token] = ['key' => $key, 'lease' => $lease];
        return $token;
    }

    public function release(string $key, string $token): void
    {
        $owned = $this->leases[$token] ?? null;
        if ($owned === null || $owned['key'] !== $key) {
            return;
        }
        unset($this->leases[$token]);
        $owned['lease']->release();
    }
}
