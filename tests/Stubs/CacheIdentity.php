<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs;

use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;

final readonly class CacheIdentity implements CacheIdentityProviderInterface
{
    public function __construct(private ?string $identity) {}

    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return $this->identity;
    }
}
