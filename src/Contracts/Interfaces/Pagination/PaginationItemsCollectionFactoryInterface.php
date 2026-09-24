<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Pagination;

/**
 * Фабрика коллекции для items.
 */
interface PaginationItemsCollectionFactoryInterface
{
    /**
     * @param array<array-key, mixed> $items
     */
    public function make(array $items): array|object;
}
