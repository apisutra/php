<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory\Catalog;

use ApiSutra\Contracts\Interfaces\Inventory\ServiceClassResolverInterface;

/**
 * Резолвер сервис-класса по статической map `requestClass → serviceClass`.
 *
 * Используется MultiServiceResponseDtoCatalogFactory: на этапе сборки каталога
 * мы уже знаем, какому сервису принадлежит каждый request (через
 * inventory.all()), и просто запоминаем эту связку.
 */
final readonly class MapServiceClassResolver implements ServiceClassResolverInterface
{
    /**
     * @param array<class-string, class-string> $map
     */
    public function __construct(
        private array $map,
    ) {
    }

    #[\Override]
    public function resolve(string $requestClass): ?string
    {
        return $this->map[$requestClass] ?? null;
    }
}
