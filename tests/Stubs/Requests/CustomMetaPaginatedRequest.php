<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Pagination\AbstractPaginatedRequest;
use ApiSutra\VO\Metadata\PaginationMeta;

#[Get('/meta-override')]
#[Pagination(pageParam: 'page', limitParam: 'limit')]
final class CustomMetaPaginatedRequest extends AbstractPaginatedRequest
{
    public static bool $extractCalled = false;

    public static function reset(): void
    {
        self::$extractCalled = false;
    }

    public function extractMeta(array $response): PaginationMeta
    {
        self::$extractCalled = true;

        return new PaginationMeta(
            total: 0,
            currentPage: 1,
            perPage: 10,
            hasMore: false,
            nextCursor: null,
        );
    }
}
