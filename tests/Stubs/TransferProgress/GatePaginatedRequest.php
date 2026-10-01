<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\TransferProgress;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/paginated')]
final class GatePaginatedRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Query] public ?int $page = null,
        #[Query] public ?int $limit = null,
    ) {
    }
}
