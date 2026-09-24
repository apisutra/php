<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Request\RequestOptions;
use Override;

#[Get('/runtime-scoped')]
final class RuntimeScopedRequest extends AbstractRequest
{
    #[Override]
    public function getOptions(): RequestOptions
    {
        return RequestOptions::empty()->withAuthScope('secondary');
    }

    #[Override]
    public function getAuthScopeOverride(): ?string
    {
        return 'secondary';
    }
}
