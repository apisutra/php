<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Behavior\Idempotent;

#[Idempotent]
final class RetryIdempotentRequest extends RetryPolicyRequest {}
