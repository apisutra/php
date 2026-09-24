<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Auth;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\SdkException;
use Throwable;

final class AuthLockBackendException extends SdkException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct(new Message('errors.auth_lock_backend_failed'), previous: $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this);
    }
}
