<?php

declare(strict_types=1);

namespace ApiSutra\Auth\OAuth2\Internal;

use ApiSutra\Contracts\Interfaces\Auth\AuthLockLeaseInterface;
use ApiSutra\Contracts\Interfaces\Auth\AuthLockProviderInterface;
use stdClass;

/** Владение одним credential в процессе: истечение TTL не передаёт живой refresh второму владельцу. */
final class CredentialRefreshState implements AuthLockProviderInterface
{
    private ?object $owner = null;

    public function isRefreshing(): bool
    {
        return $this->owner !== null;
    }

    public function acquire(string $key, int $ttlSeconds): ?AuthLockLeaseInterface
    {
        if ($this->owner !== null) {
            return null;
        }
        $owner = $this->owner = new stdClass();
        return new class ($this, $owner) implements AuthLockLeaseInterface {
            public function __construct(private CredentialRefreshState $state, private object $owner)
            {
            }
            public function release(): bool
            {
                return $this->state->release($this->owner);
            }
        };
    }

    public function release(object $owner): bool
    {
        if ($this->owner !== $owner) {
            return false;
        }
        $this->owner = null;
        return true;
    }
}
