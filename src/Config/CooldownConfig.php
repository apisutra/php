<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;

/** Null у maxAdditionalWaitMs: бюджет исполнения, а без него запасные 1000 ms. */
final readonly class CooldownConfig
{
    public function __construct(
        public bool $enabled = true,
        public ?string $group = null,
        public RateLimitBehavior $behavior = RateLimitBehavior::Wait,
        public ?string $identity = null,
        public ?int $maxAdditionalWaitMs = null,
    ) {
        if (
            ($group !== null && trim($group) === '') || ($identity !== null && trim($identity) === '')
            || ($maxAdditionalWaitMs !== null && ($maxAdditionalWaitMs < 0 || $maxAdditionalWaitMs > intdiv(PHP_INT_MAX, 1000)))
        ) {
            throw new ConfigurationException(new Message('rate_limit.invalid_cooldown_config'));
        }
    }
}
