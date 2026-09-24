<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Behavior;

use Attribute;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class RateLimit
{
    public function __construct(
        public ?int $limit = null,
        public ?int $period = null,
        public RateLimitBehavior $behavior = RateLimitBehavior::Wait,
        public ?string $key = null,
        public ?bool $includeClientQuota = null,
    ) {
    }
}
