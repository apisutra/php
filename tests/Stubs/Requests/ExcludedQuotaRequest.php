<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Behavior\RateLimit;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[Get('/quota')]
#[RateLimit(limit: 1, period: 60, behavior: RateLimitBehavior::Throw, includeClientQuota: false)]
final class ExcludedQuotaRequest extends AbstractRequest
{
}
