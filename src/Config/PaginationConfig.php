<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Contracts\Interfaces\Pagination\PaginationItemsCollectionFactoryInterface;
use ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaResolverInterface;

/**
 * Конфигурация пагинации.
 * Все поля могут быть заданы на уровне клиента и переопределены в #[Pagination].
 *
 * Нюансы:
 * - maxPages задаёт жёсткий лимит страниц, null отключает ограничение.
 */
final readonly class PaginationConfig
{
    public function __construct(
        public string $pageParam = 'page',
        public string $limitParam = 'limit',
        public ?string $cursorParam = null,
        public string $metaPath = 'meta',
        public string $itemsPath = 'data',
        public bool $offsetBased = false,
        public PaginationMetaResolverInterface|string|null $metaResolver = null,
        public ?string $itemsType = null,
        public ?string $itemsCollection = null,
        public PaginationItemsCollectionFactoryInterface|string|null $itemsCollectionFactory = null,
        public ?int $maxPages = 1000,
    ) {
    }
}
