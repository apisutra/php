<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Pagination;

/**
 * Контейнер для items в paginated-ответах.
 * Нужен, чтобы пагинатор мог извлечь items из DTO-обёртки.
 */
interface PaginationItemsContainerInterface
{
    /**
     * @return array<array-key, mixed>|object
     */
    public function items(): array|object;

    /**
     * @param array<array-key, mixed>|object $items
     */
    public function withItems(array|object $items): static;
}
