<?php

declare(strict_types=1);

namespace ApiSutra\VO\Metadata;

use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;

/**
 * Метаданные пагинации для результата запроса.
 *
 * Содержит информацию о текущей странице, общем количестве элементов,
 * элементов на странице и наличии следующей страницы.
 *
 * Используется в:
 * - DefaultPaginationMetaResolver::resolve() - создаёт из ответа провайдера
 * - PaginatedResult::meta() - хранит метаданные пагинированного результата
 * - Paginator::resolveMeta() - извлекает метаданные для управления пагинацией
 * - PaginableInterface::extractMeta() - извлекает метаданные из response data
 */
readonly class PaginationMeta implements ResultMeta
{
    /**
     * @param int|null $total Общее количество элементов (null если неизвестно)
     * @param int $currentPage Текущая страница (начиная с 1)
     * @param int $perPage Количество элементов на странице
     * @param bool $hasMore Есть ли следующая страница
     * @param string|null $nextCursor Курсор для cursor-based пагинации (null если не используется)
     */
    public function __construct(
        public ?int $total,
        public int $currentPage,
        public int $perPage,
        public bool $hasMore,
        public ?string $nextCursor = null,
    ) {
    }

    /**
     * Вычисляет общее количество страниц на основе total и perPage.
     *
     * @return int|null Количество страниц или null если total неизвестен или perPage <= 0
     */
    public function totalPages(): ?int
    {
        if ($this->total === null || $this->perPage <= 0) {
            return null;
        }

        return (int) ceil($this->total / $this->perPage);
    }
}
