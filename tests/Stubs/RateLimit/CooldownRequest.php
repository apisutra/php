<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RateLimit;

use ApiSutra\Attributes\Behavior\Cooldown;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;

#[Cooldown(group: 'reports', maxAdditionalWaitMs: 2000)]
final class CooldownRequest extends RetryPolicyRequest
{
}
