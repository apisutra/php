<?php

declare(strict_types=1);

namespace ApiSutra\DataTransfer;

use ApiSutra\Contracts\Interfaces\Pagination\PaginationItemsContainerInterface;

/**
 * Базовый DTO-контейнер для paginated-ответов.
 *
 * Зачем:
 * - даёт единый контракт items()/withItems() для ядра,
 * - убирает бойлерплейт в пользовательских DTO.
 *
 * Как использовать:
 * - для простых контейнеров достаточно унаследоваться без конструктора;
 * - если нужны дополнительные поля — добавьте их в конструктор и
 *   переопределите withItems(), чтобы сохранить значения.
 */
abstract readonly class AbstractPaginationContainerDto extends AbstractDto implements PaginationItemsContainerInterface
{
    public function __construct(
        public array|object|null $items = null,
    ) {
    }

    public function items(): array|object
    {
        return $this->items ?? [];
    }

    public function withItems(array|object $items): static
    {
        return new static(items: $items);
    }
}
