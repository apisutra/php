<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Pagination;

use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\VO\Metadata\PaginationMeta;

interface PaginableInterface
{
    public function withPage(int $page): RequestExecutionInterface;
    public function withLimit(int $limit): RequestExecutionInterface;

    /**
     * Опционально — для cursor-based
     */
    public function withCursor(?string $cursor): RequestExecutionInterface;

    /**
     * Извлечение меты из ответа (приоритет над атрибутом)
     */
    public function extractMeta(array $response): PaginationMeta;
}
