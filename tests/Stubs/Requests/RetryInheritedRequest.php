<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Behavior\Retry;

#[Retry(baseDelay: 0)]
final class RetryInheritedRequest extends RetryPolicyRequest {}
