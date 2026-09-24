<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Behavior\RateLimit;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[Get('/quota')]
#[RateLimit(includeClientQuota: false)]
final class ExcludedOnlyQuotaRequest extends AbstractRequest
{
}
