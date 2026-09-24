<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Continuation;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/start')]
final class UndeclaredRequest extends AbstractRequest
{
}
