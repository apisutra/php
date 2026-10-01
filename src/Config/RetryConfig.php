<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Localization\Message;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Transport\ConnectionException;

final readonly class RetryConfig
{
    /**
     * @param array<int> $retryOn
     * @param array<string> $retryExceptions
     * @param list<HttpMethod> $safeMethods Методы, безопасность повтора которых подтверждена для клиента.
     */
    public function __construct(
        public int $attempts = 3,
        public int $baseDelay = 100,
        public int $maxDelay = 10000,
        public BackoffStrategy $backoff = BackoffStrategy::Exponential,
        public bool $jitter = true,
        public array $retryOn = [408, 429, 500, 502, 503, 504],
        public array $retryExceptions = [ConnectionException::class],
        public ?int $totalTimeoutMs = null,
        public array $safeMethods = [HttpMethod::GET, HttpMethod::PUT, HttpMethod::DELETE],
    ) {
        $this->validate();
    }

    /**
     * Копирование полей, доступных атрибуту и runtime-опциям; остальные сохраняются.
     *
     * @internal
     * @param array<int>|null $retryOn
     */
    public function withOverrides(
        ?int $attempts = null,
        ?int $baseDelay = null,
        ?int $maxDelay = null,
        ?BackoffStrategy $backoff = null,
        ?bool $jitter = null,
        ?array $retryOn = null,
    ): self {
        return new self(
            attempts: $attempts ?? $this->attempts,
            baseDelay: $baseDelay ?? $this->baseDelay,
            maxDelay: $maxDelay ?? $this->maxDelay,
            backoff: $backoff ?? $this->backoff,
            jitter: $jitter ?? $this->jitter,
            retryOn: $retryOn ?? $this->retryOn,
            retryExceptions: $this->retryExceptions,
            totalTimeoutMs: $this->totalTimeoutMs,
            safeMethods: $this->safeMethods,
        );
    }

    /** Валидировать параметры retry. */
    private function validate(): void
    {
        foreach ($this->safeMethods as $method) {
            if (!$method instanceof HttpMethod) {
                throw new ConfigurationException(new Message('configuration.retryconfig_safemethods_must_contain_httpmethod_values'));
            }
        }
        if ($this->attempts < 1) {
            throw new ConfigurationException(new Message('configuration.retryconfig_attempts_must_be_1'));
        }

        if ($this->baseDelay < 0) {
            throw new ConfigurationException(new Message('configuration.retryconfig_basedelay_must_be_0'));
        }

        if ($this->maxDelay < $this->baseDelay) {
            throw new ConfigurationException(new Message('configuration.retryconfig_maxdelay_must_be_basedelay'));
        }

        if ($this->totalTimeoutMs !== null && $this->totalTimeoutMs < 1) {
            throw new ConfigurationException(new Message('configuration.retryconfig_totaltimeoutms_must_be_1'));
        }
    }
}
