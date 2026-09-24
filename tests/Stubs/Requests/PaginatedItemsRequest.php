<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/items')]
#[Pagination(itemsPath: 'data.items')]
final class PaginatedItemsRequest extends AbstractPaginatedRequest
{
}
