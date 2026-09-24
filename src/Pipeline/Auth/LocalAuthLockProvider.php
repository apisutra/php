<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Auth;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Auth\AuthLockLeaseInterface;
use ApiSutra\Contracts\Interfaces\Auth\AuthLockProviderInterface;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Timing\SystemClock;

/** Область действия — экземпляр службы; состояние между процессами не разделяется. */
final class LocalAuthLockProvider implements AuthLockProviderInterface
{
    /** @var array<string, array{owner: string, expires: int}> */
    private array $locks = [];

    public function __construct(private readonly ClockInterface $clock = new SystemClock())
    {
    }

    public function acquire(string $key, int $ttlSeconds): ?AuthLockLeaseInterface
    {
        if ($ttlSeconds <= 0 || $ttlSeconds > intdiv(PHP_INT_MAX - $this->clock->monotonicMs(), 1000)) {
            throw new ConfigurationException(new Message('pipeline.auth_lock_ttl_must_be_positive_and_fit_within'));
        }
        $now = $this->clock->monotonicMs();
        foreach ($this->locks as $name => $lock) {
            if ($lock['expires'] <= $now) {
                unset($this->locks[$name]);
            }
        }
        if (isset($this->locks[$key])) {
            return null;
        }
        $owner = bin2hex(random_bytes(16));
        $this->locks[$key] = ['owner' => $owner, 'expires' => $now + $ttlSeconds * 1000];
        return new LocalAuthLockLease($this, $key, $owner);
    }

    public function release(string $key, string $owner): bool
    {
        $lock = $this->locks[$key] ?? null;
        if ($lock === null || !hash_equals($lock['owner'], $owner)) {
            return false;
        }
        unset($this->locks[$key]);
        return $lock['expires'] > $this->clock->monotonicMs();
    }
}
