<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Pagination\AbstractPaginatedRequest;
use ApiSutra\VO\Metadata\PaginationMeta;

#[Get('/meta-sequence')]
#[Pagination(pageParam: 'page', limitParam: 'limit')]
final class MetaSequencePaginatedRequest extends AbstractPaginatedRequest
{
    /**
     * @var array<int, PaginationMeta>
     */
    private static array $metaQueue = [];

    public static function setMetaQueue(array $queue): void
    {
        self::$metaQueue = $queue;
    }

    public static function reset(): void
    {
        self::$metaQueue = [];
    }

    public function __construct(
        #[Query]
        public ?int $page = null,
        #[Query]
        public ?int $limit = null,
    ) {}

    public function extractMeta(array $response): PaginationMeta
    {
        $next = array_shift(self::$metaQueue);
        if ($next instanceof PaginationMeta) {
            return $next;
        }

        return new PaginationMeta(
            total: null,
            currentPage: 1,
            perPage: 0,
            hasMore: false,
            nextCursor: null,
        );
    }
}
