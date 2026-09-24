<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Resolver;

use ApiSutra\Contracts\Interfaces\Resolver\RequestNamespaceProviderInterface;

final class NamespaceProviderClient extends BaseStubClient implements RequestNamespaceProviderInterface
{
    /**
     * @return array<int, string>
     */
    public function requestNamespaces(): array
    {
        return [
            'ApiSutra\\Tests\\Stubs\\Requests',
        ];
    }
}
