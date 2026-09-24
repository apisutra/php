<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Pagination;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/pages')]
final class PageRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Query] public ?int $page = null,
        #[Query] public ?int $limit = null,
        #[Query]
        public ?int $ms = null,
        #[Query]
        public ?int $status = null,
    ) {
    }
}
