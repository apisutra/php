<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Retry;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\SdkException;
use Throwable;

/** Ошибка пользовательской проверки не является причиной повторять её или HTTP. */
final class RetrySafetyException extends SdkException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct(new Message('errors.retry_safety_check_failed'), 0, $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this);
    }
}
