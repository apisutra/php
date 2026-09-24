<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Localization\Message;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use Psr\SimpleCache\CacheInterface;

final readonly class RateLimitConfig
{
    public function __construct(
        public int $limit = 100,
        public int $period = 60,
        public RateLimitBehavior $behavior = RateLimitBehavior::Wait,
        public ?CacheInterface $store = null,
        public ?string $key = null,
    ) {
        if ($limit < 1) {
            throw new ConfigurationException(new Message('configuration.ratelimitconfig_limit_must_be_positive'));
        }
        if ($period < 1 || $period > intdiv(PHP_INT_MAX, 1_000_000)) {
            throw new ConfigurationException(new Message('configuration.ratelimitconfig_period_must_be_positive_and_representable_in_microseconds'));
        }
    }
}
