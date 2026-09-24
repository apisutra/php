<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\AuthScope;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\Enums\AuthScopeKey;

#[Get('/auth-scope-enum')]
#[Returns(SimpleResponseDto::class)]
#[AuthScope(AuthScopeKey::System)]
final class ScopedAuthEnumRequest extends AbstractRequest
{
}
