<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Behavior\Retry;

#[Retry(enabled: false, baseDelay: 0)]
final class RetryDisabledRequest extends RetryPolicyRequest {}
