<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderB\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/provider-b/search')]
#[Pagination(pageParam: 'page', limitParam: 'limit')]
final class ProviderBSearchRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Query]
        public string $query,
        #[Query]
        public ?int $page = null,
        #[Query]
        public ?int $limit = null,
    ) {}
}
