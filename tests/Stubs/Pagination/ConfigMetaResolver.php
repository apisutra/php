<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Pagination;

use ApiSutra\Config\PaginationConfig;
use ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaResolverInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Metadata\PaginationMeta;
use ApiSutra\VO\Pipeline\PipelineContext;

final class ConfigMetaResolver implements PaginationMetaResolverInterface
{
    public static bool $called = false;

    public static function reset(): void
    {
        self::$called = false;
    }

    public function resolve(
        AbstractRequest $request,
        array $response,
        array $meta,
        PaginationConfig $config,
        ?PipelineContext $context
    ): PaginationMeta
    {
        self::$called = true;

        return new PaginationMeta(
            total: 222,
            currentPage: 2,
            perPage: 20,
            hasMore: true,
            nextCursor: 'next',
        );
    }
}
