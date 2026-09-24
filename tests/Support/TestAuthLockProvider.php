<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Contracts\Interfaces\Auth\AuthLockLeaseInterface;
use ApiSutra\Contracts\Interfaces\Auth\AuthLockProviderInterface;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Pipeline\Auth\LocalAuthLockProvider;
use ApiSutra\Timing\SystemClock;
use Closure;
use RuntimeException;

final class TestAuthLockProvider implements AuthLockProviderInterface
{
    public bool $busy = false;
    public bool $failAcquire = false;
    public bool $failRelease = false;
    public int $releases = 0;
    public ?Closure $onAcquire = null;
    /** @var list<string> */
    public array $keys = [];
    private LocalAuthLockProvider $local;

    public function __construct(ClockInterface $clock = new SystemClock())
    {
        $this->local = new LocalAuthLockProvider($clock);
    }

    public function acquire(string $key, int $ttlSeconds): ?AuthLockLeaseInterface
    {
        $this->keys[] = $key;
        ($this->onAcquire)?->__invoke($key);
        if ($this->failAcquire) {
            throw new RuntimeException('fixture-backend-secret');
        }
        if ($this->busy) {
            return null;
        }
        $lease = $this->local->acquire($key, $ttlSeconds);
        return $lease === null ? null : new class ($this, $lease) implements AuthLockLeaseInterface {
            public function __construct(private TestAuthLockProvider $provider, private AuthLockLeaseInterface $lease)
            {
            }

            public function release(): bool
            {
                $this->provider->releases++;
                if ($this->provider->failRelease) {
                    throw new RuntimeException('fixture-backend-secret');
                }
                return $this->lease->release();
            }
        };
    }
}
