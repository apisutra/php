<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Auth;

interface AuthLockLeaseInterface
{
    /** Атомарно освобождает только собственную блокировку; false при утрате владения. */
    public function release(): bool;
}
