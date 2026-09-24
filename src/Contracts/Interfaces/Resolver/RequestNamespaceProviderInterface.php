<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Resolver;

interface RequestNamespaceProviderInterface
{
    /**
     * @return array<int, string>
     */
    public function requestNamespaces(): array;
}
