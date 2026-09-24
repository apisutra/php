<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\NoAuth;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;

#[Get('/no-auth')]
#[NoAuth]
#[Returns(SimpleResponseDto::class)]
final class NoAuthRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $payload,
    ) {}
}
