<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\RateLimit;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[Get('/rate-limit-key')]
#[RateLimit(limit: 1, period: 60, behavior: RateLimitBehavior::Throw, key: 'attr-key')]
final class RateLimitKeyRequest extends AbstractRequest
{
}
