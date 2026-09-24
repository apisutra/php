<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Idempotent;
use ApiSutra\Core\AbstractRequest;

#[Get('/idempotent')]
#[Idempotent(header: 'X-Idempotency')]
final class IdempotentRequest extends AbstractRequest
{
}
