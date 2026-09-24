<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Pagination\AbstractPaginatedRequest;
use ApiSutra\Tests\Stubs\Dto\PaginationContainerDto;
use ApiSutra\Tests\Stubs\Dto\PaginationItemDto;
use ApiSutra\Tests\Stubs\Pagination\TestItemCollectionFactory;

#[Get('/wrapped-factory')]
#[Returns(response: PaginationContainerDto::class)]
#[Pagination(
    itemsPath: 'response.result',
    itemsType: PaginationItemDto::class,
    itemsCollectionFactory: TestItemCollectionFactory::class,
)]
final class FactoryWrappedPaginatedRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Query]
        public ?int $page = null,
        #[Query]
        public ?int $limit = null,
    ) {}
}
