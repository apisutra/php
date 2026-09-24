<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Resolver;

use ApiSutra\Contracts\Interfaces\Resolver\RequestNamespaceProviderInterface;
use Override;

final class MutableNamespaceClient extends BaseStubClient implements RequestNamespaceProviderInterface
{
    /** @var list<string> */
    public array $namespaces = ['Example\\First'];

    #[Override]
    public function requestNamespaces(): array
    {
        return $this->namespaces;
    }
}
