<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Response\RawResponse;

#[RawResponse]
final class RawStringRequest extends RetryPolicyRequest
{
}
