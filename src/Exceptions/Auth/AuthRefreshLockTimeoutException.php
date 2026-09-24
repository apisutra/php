<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Auth;

use ApiSutra\Localization\Message;
use Throwable;
use ApiSutra\Exceptions\Transport\TimeoutException;

final class AuthRefreshLockTimeoutException extends TimeoutException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(new Message('errors.timed_out_waiting_for_token_refresh'), previous: $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this);
    }
}
