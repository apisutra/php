<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Auth;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Request\UnauthorizedException;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Http\ProviderResponse;
use Throwable;
use ApiSutra\Exceptions\Core\SdkException;

/** Основной 401 и диагностика восстановления доступны отдельно от safe context. */
final class AuthRefreshFailedException extends UnauthorizedException
{
    public function __construct(
        ProviderResponse $response,
        public readonly ?ExecutionResult $dependencyResult,
        public readonly Throwable $recoveryException,
        ?SdkException $previous = null,
    ) {
        parent::__construct(new Message('errors.failed_to_recover_authentication_after_401'), $response, previous: $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->response, $this->dependencyResult, $this->recoveryException, $this);
    }
}
