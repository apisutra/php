<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Request\AuthScope;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;

#[Get('/auth-scope')]
#[Returns(SimpleResponseDto::class)]
#[AuthScope('system')]
final class ScopedAuthRequest extends AbstractRequest
{
}
