<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Behavior\Timeout;

#[Timeout(seconds: 8, connectTimeout: 4)]
final class TimeoutPolicyRequest extends RetryPolicyRequest {}
