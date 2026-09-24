<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Catalog;

interface ProviderCatalogRegistryInterface
{
    public function has(string $key): bool;

    public function get(string $key): ?ProviderCatalogInterface;

    /**
     * @return array<string, ProviderCatalogInterface>
     */
    public function all(): array;

    /**
     * @return array<string, RequestBoundProviderCatalogInterface>
     */
    public function forRequest(string $requestClass): array;
}
