<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\RateLimiting;

use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Localization\Message;
use ApiSutra\VO\Http\ProviderResponse;
use Throwable;

final class CooldownBackendException extends SdkException
{
    public function __construct(
        public readonly string $stage,
        ?Throwable $previous = null,
        public readonly ?ProviderResponse $lastResponse = null,
    ) {
        parent::__construct(new Message('rate_limit.cooldown_backend_failed'), previous: $previous);
    }

    /** @return array{reason: string, stage: string} */
    public function context(): array
    {
        return ['reason' => 'cooldown_backend_error', 'stage' => $this->stage];
    }

    protected function copyForLocalization(): static
    {
        return new self($this->stage, $this, $this->lastResponse);
    }
}
