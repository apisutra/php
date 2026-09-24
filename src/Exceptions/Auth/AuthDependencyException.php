<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Auth;

use ApiSutra\Localization\Message;
use Throwable;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Result\ExecutionResult;

/** Внутренняя доставка результата refresh до первого основного HTTP. */
final class AuthDependencyException extends SdkException
{
    public function __construct(public readonly ExecutionResult $dependencyResult, ?Throwable $previous = null)
    {
        parent::__construct(new Message('errors.failed_to_refresh_authentication'), previous: $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->dependencyResult, $this);
    }
}
