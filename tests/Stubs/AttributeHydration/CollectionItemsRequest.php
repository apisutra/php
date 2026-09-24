<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Pagination\AbstractPaginatedRequest;
use ApiSutra\Tests\Stubs\Pagination\TestItemCollection;
use ApiSutra\Tests\Stubs\Pagination\TestItemCollectionFactory;

#[Get('/items')]
#[Pagination(itemsPath: 'response.rows', itemsType: Row::class, itemsCollection: TestItemCollection::class)]
final class CollectionItemsRequest extends AbstractPaginatedRequest
{
}
