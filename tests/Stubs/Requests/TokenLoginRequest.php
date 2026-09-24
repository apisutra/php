<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\TokenLoginResponse;

#[Post('/token')]
#[Returns(TokenLoginResponse::class)]
final class TokenLoginRequest extends AbstractRequest
{
    public function __construct(
        #[Body] public string $username,
        #[Body] public string $password,
    ) {
    }
}
