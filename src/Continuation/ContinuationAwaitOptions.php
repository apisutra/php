<?php

declare(strict_types=1);

namespace ApiSutra\Continuation;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;

/**
 * Опции polling-цикла для provider async-await.
 *
 * Инварианты:
 * - maxAttempts >= 1;
 * - intervalMs >= 0;
 * - валидация выполняется в конструкторе fail-fast.
 *
 * @see docs/guides/provider-async-await.md
 */
final readonly class ContinuationAwaitOptions
{
    public function __construct(
        public int $maxAttempts = 30,
        public int $intervalMs = 1000,
    ) {
        if ($this->maxAttempts < 1) {
            throw new ContinuationConfigurationException(new Message('continuation.continuationawaitoptions_maxattempts_must_be_1'));
        }

        if ($this->intervalMs < 0) {
            throw new ContinuationConfigurationException(new Message('continuation.continuationawaitoptions_intervalms_must_be_0'));
        }
    }
}
