<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/paginated')]
#[Pagination(pageParam: 'page', limitParam: 'limit', cursorParam: 'cursor')]
final class PaginatedRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Query]
        public ?int $page = null,
        #[Query]
        public ?int $limit = null,
        #[Query]
        public ?string $cursor = null,
    ) {}
}
