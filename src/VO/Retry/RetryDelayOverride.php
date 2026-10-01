<?php

declare(strict_types=1);

namespace ApiSutra\VO\Retry;

use ApiSutra\Enums\RateLimiting\BackoffStrategy;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;

/** Частичная настройка задержки: null наследует атрибут или конфигурацию клиента. */
final readonly class RetryDelayOverride
{
    public function __construct(
        public ?int $baseDelay = null,
        public ?int $maxDelay = null,
        public ?BackoffStrategy $backoff = null,
        public ?bool $jitter = null,
    ) {
        if ($baseDelay === null && $maxDelay === null && $backoff === null && $jitter === null) {
            throw new ConfigurationException(new Message('request.retry_delay_override_must_not_be_empty'));
        }
        if (($baseDelay !== null && $baseDelay < 0) || ($maxDelay !== null && $maxDelay < 0)) {
            throw new ConfigurationException(new Message('request.retry_delay_must_be_non_negative'));
        }
    }
}
