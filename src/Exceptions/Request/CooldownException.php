<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Request;

use ApiSutra\Localization\Message;
use ApiSutra\VO\Http\ProviderResponse;

/** Локальный отказ по наблюдённому серверному запрету, без нового HTTP-ответа. */
final class CooldownException extends RateLimitException
{
    public function __construct(public readonly int $retryAfterMs, ?ProviderResponse $lastResponse = null)
    {
        parent::__construct(
            new Message('rate_limit.server_cooldown_active'),
            null,
            intdiv($retryAfterMs, 1000) + ($retryAfterMs % 1000 === 0 ? 0 : 1),
            lastResponse: $lastResponse,
        );
    }

    /** @return array{reason: string, stage: string, retryAfter: int|null, retryAfterMs: int} */
    public function context(): array
    {
        return ['reason' => 'server_cooldown_active', 'stage' => 'cooldown', 'retryAfter' => $this->retryAfter, 'retryAfterMs' => $this->retryAfterMs];
    }

    public function withLastResponse(?ProviderResponse $response): self
    {
        return new self($this->retryAfterMs, $response);
    }

    protected function copyForLocalization(): static
    {
        return new self($this->retryAfterMs, $this->lastResponse);
    }
}
