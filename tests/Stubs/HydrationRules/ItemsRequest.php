<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/items')]
#[Pagination(itemsPath: 'response.rows', itemsType: RecordDto::class)]
final class ItemsRequest extends AbstractPaginatedRequest
{
}
