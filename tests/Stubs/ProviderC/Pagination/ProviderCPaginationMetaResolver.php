<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC\Pagination;

use ApiSutra\Config\PaginationConfig;
use ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaResolverInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Metadata\PaginationMeta;
use ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ProviderCPaginationMetaResolver implements PaginationMetaResolverInterface
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
    ): PaginationMeta
    {
        $count = (int) ($meta['count'] ?? 0);
        $page = (int) ($meta['page'] ?? 1);
        $rows = (int) ($meta['rows'] ?? 0);
        $hasMore = $meta['has_more'] ?? null;

        if ($hasMore === null) {
            $hasMore = $rows > 0 ? $page * $rows < $count : false;
        }

        return new PaginationMeta(
            total: $count > 0 ? $count : null,
            currentPage: $page,
            perPage: $rows,
            hasMore: (bool) $hasMore,
            nextCursor: null,
        );
    }
}
