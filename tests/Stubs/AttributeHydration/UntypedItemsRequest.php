<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Pagination\AbstractPaginatedRequest;
use ApiSutra\Tests\Stubs\Pagination\TestItemCollection;
use ApiSutra\Tests\Stubs\Pagination\TestItemCollectionFactory;

#[Get('/items')]
#[Pagination(itemsPath: 'response.rows', itemsCollectionFactory: TestItemCollectionFactory::class)]
final class UntypedItemsRequest extends AbstractPaginatedRequest
{
}
