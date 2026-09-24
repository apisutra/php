<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Auth;

use ApiSutra\Auth\OAuth2\OAuth2FailureReason;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Localization\Message;
use Throwable;

final class OAuth2Exception extends SdkException
{
    public function __construct(public readonly OAuth2FailureReason $reason, ?Throwable $previous = null)
    {
        parent::__construct(new Message('oauth2.operation_failed', ['reason' => $reason->value]), previous: $previous);
    }

    protected function copyForLocalization(): static
    {
        return new self($this->reason, $this);
    }
}
