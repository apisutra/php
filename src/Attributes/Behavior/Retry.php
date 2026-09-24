<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Behavior;

use Attribute;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Retry
{
    public function __construct(
        public bool $enabled = true,
        public int $attempts = 3,
        public int $baseDelay = 100,
        public int $maxDelay = 10000,
        public BackoffStrategy $backoff = BackoffStrategy::Exponential,
        public bool $jitter = true,
        public array $retryOn = [429, 500, 502, 503, 504],
        public ?bool $safe = null,
    ) {
    }
}
