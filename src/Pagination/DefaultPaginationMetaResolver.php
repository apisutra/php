<?php

declare(strict_types=1);

namespace ApiSutra\Pagination;

use ApiSutra\Config\PaginationConfig;
use ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaResolverInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;
use ApiSutra\VO\Metadata\PaginationMeta;
use ApiSutra\VO\Pipeline\PipelineContext;

final readonly class DefaultPaginationMetaResolver implements PaginationMetaResolverInterface
{
    /**
     * @param array<string, mixed> $response Ответ провайдера
     * @param array<string, mixed> $meta Извлечённая meta-часть ответа
     */
    public function resolve(
        AbstractRequest $request,
        array $response,
        array $meta,
        PaginationConfig $config,
        ?PipelineContext $context
    ): PaginationMeta {
        $total = $meta['total'] ?? $meta['count'] ?? null;
        $currentPage = (int) ($meta['page'] ?? $meta['currentPage'] ?? $meta['current_page'] ?? $this->requestedPage($config, $context));
        $perPage = (int) ($meta['per_page'] ?? $meta['perPage'] ?? $meta['limit'] ?? $meta['page_size'] ?? 0);
        $nextCursor = $meta['next_cursor'] ?? $meta['nextCursor'] ?? null;
        $hasMore = $meta['has_more'] ?? $meta['hasMore'] ?? null;

        if ($hasMore === null) {
            if ($nextCursor !== null) {
                $hasMore = true;
            } elseif ($total !== null && $perPage > 0) {
                $hasMore = $currentPage * $perPage < $total;
            } else {
                $hasMore = false;
            }
        }

        return new PaginationMeta(
            total: $total !== null ? (int) $total : null,
            currentPage: $currentPage,
            perPage: $perPage,
            hasMore: (bool) $hasMore,
            nextCursor: $nextCursor !== null ? (string) $nextCursor : null,
        );
    }

    private function requestedPage(PaginationConfig $config, ?PipelineContext $context): int
    {
        $page = $context?->paginationOptions?->getPage();
        if ($page === null) {
            return 1;
        }
        if (!$config->offsetBased) {
            return $page;
        }
        $limit = $context->paginationOptions->getLimit();
        if ($page < 0 || $limit === null || $limit < 1) {
            return 1;
        }
        $index = intdiv($page, $limit);
        if ($index === PHP_INT_MAX) {
            throw new ConfigurationException(new Message('pagination.invalid_page_range'));
        }
        return $index + 1;
    }
}
