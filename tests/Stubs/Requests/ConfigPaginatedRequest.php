<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/config-paginated')]
final class ConfigPaginatedRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Query]
        public ?int $offset = null,
        #[Query]
        public ?int $size = null,
    ) {}
}
