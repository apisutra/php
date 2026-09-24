<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\Attributes\Behavior\Cache;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Auth\CacheCredentialIdentity;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Core\AbstractRequest;

#[Get('/tenants')]
#[Cache(key: 'tenant-summary')]
final class TenantCacheRequest extends AbstractRequest implements CacheIdentityProviderInterface
{
    public function __construct(#[Header('X-Tenant-Id')] public ?string $tenant = 'default') {}

    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return $this->tenant === null ? null : CacheCredentialIdentity::forRequest($this->tenant, $request, ['X-Tenant-Id']);
    }
}
