<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\RateLimiting;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\VO\Http\ProviderResponse;
use Throwable;

final class RateLimitBackendException extends SdkException
{
    public function __construct(
        ?Throwable $previous = null,
        public readonly ?ProviderResponse $lastResponse = null,
    ) {
        parent::__construct(new Message('errors.rate_limit_store_failed'), previous: $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this, $this->lastResponse);
    }
}
