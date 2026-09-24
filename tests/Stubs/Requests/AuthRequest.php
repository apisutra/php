<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;

#[Get('/auth')]
#[Returns(SimpleResponseDto::class)]
final class AuthRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $payload,
    ) {}
}
