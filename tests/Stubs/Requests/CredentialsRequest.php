<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\AuthScope;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Enums\AuthScopeKey;

#[Post('/credentials')]
#[AuthScope(AuthScopeKey::System)]
final class CredentialsRequest extends AbstractRequest
{
    public function __construct(
        #[Body(nested: 'payload.auth.login')]
        public ?string $login = null,
        #[Query('api_key')]
        public ?string $apiKey = null,
        #[Body]
        public ?string $plain = null,
    ) {}
}
