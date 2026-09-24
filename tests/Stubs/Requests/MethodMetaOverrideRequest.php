<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaOverrideInterface;
use ApiSutra\Pagination\AbstractPaginatedRequest;
use ApiSutra\Tests\Stubs\Pagination\AttributeMetaResolver;
use ApiSutra\VO\Metadata\PaginationMeta;
use ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/meta-method')]
#[Pagination(metaResolver: AttributeMetaResolver::class)]
final class MethodMetaOverrideRequest extends AbstractPaginatedRequest implements PaginationMetaOverrideInterface
{
    public static ?string $lastTraceId = null;

    public static function reset(): void
    {
        self::$lastTraceId = null;
    }

    public function resolvePaginationMeta(
        array $response,
        array $meta,
        PaginationConfig $config,
        ?PipelineContext $context
    ): PaginationMeta
    {
        self::$lastTraceId = $context?->traceId;

        return new PaginationMeta(
            total: 333,
            currentPage: 3,
            perPage: 30,
            hasMore: false,
            nextCursor: null,
        );
    }
}
