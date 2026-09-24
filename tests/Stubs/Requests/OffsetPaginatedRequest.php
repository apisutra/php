<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/offset-paginated')]
#[Pagination(pageParam: 'offset', limitParam: 'limit', offsetBased: true)]
final class OffsetPaginatedRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Query]
        public ?int $offset = null,
        #[Query]
        public ?int $limit = null,
    ) {}
}
