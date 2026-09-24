<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Response\RawResponse;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;

#[RawResponse]
#[Returns(SimpleResponseDto::class)]
final class RawDtoRequest extends RetryPolicyRequest
{
}
