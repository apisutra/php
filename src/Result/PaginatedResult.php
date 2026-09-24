<?php

declare(strict_types=1);

namespace ApiSutra\Result;

use ApiSutra\Collections\ResultCollection;
use ApiSutra\VO\Metadata\PaginationMeta;

readonly class PaginatedResult extends ExecutionResult
{
    /**
     * @return array<array-key, mixed>|object
     */
    public function items(): array|object
    {
        if ($this->data === null) {
            return [];
        }

        return is_array($this->data) || is_object($this->data) ? $this->data : [$this->data];
    }

    /**
     * Возвращает страницы как коллекцию результатов.
     */
    public function pages(): ResultCollection
    {
        return $this->nestedResults();
    }

    public function meta(): PaginationMeta
    {
        return $this->meta instanceof PaginationMeta
            ? $this->meta
            : new PaginationMeta(total: null, currentPage: 1, perPage: 0, hasMore: false);
    }
}
