<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Behavior\Cache;
use ApiSutra\Attributes\Behavior\Execution;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Idempotent;
use ApiSutra\Attributes\Behavior\NoAuth;
use ApiSutra\Attributes\Behavior\RateLimit;
use ApiSutra\Attributes\Behavior\Timeout;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Cache\CacheMode;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[Get('/attributes')]
#[NoAuth]
#[Timeout(seconds: 5, connectTimeout: 2)]
#[RateLimit(limit: 10, period: 60, behavior: RateLimitBehavior::Wait)]
#[Idempotent(header: 'Idempotency-Key')]
#[Execution(mode: ExecutionMode::Sequential, failStrategy: FailStrategy::FailAll)]
#[Cache(ttl: 300, mode: CacheMode::Enabled, key: 'attr-cache-key')]
final class AttributeRichRequest extends AbstractRequest
{
    public function __construct(
        public string $payload,
    ) {}
}
