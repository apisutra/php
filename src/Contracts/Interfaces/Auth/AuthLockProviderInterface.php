<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Auth;

interface AuthLockProviderInterface
{
    /** Атомарный захват отсутствующего/истёкшего lease; null означает занятость. */
    public function acquire(string $key, int $ttlSeconds): ?AuthLockLeaseInterface;
}
