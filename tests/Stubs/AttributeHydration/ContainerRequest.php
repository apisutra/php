<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Pagination\AbstractPaginatedRequest;
use ApiSutra\Tests\Stubs\Dto\PaginationContainerDto;

#[Get('/items')]
#[Returns(PaginationContainerDto::class)]
#[Pagination(itemsPath: 'response.rows', itemsType: Row::class)]
final class ContainerRequest extends AbstractPaginatedRequest
{
}
