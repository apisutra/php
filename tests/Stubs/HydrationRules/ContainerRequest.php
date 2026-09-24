<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Pagination\AbstractPaginatedRequest;
use ApiSutra\Tests\Stubs\Dto\PaginationContainerDto;

#[Get('/items')]
#[Returns(PaginationContainerDto::class)]
#[Pagination(itemsPath: 'response.rows', itemsType: RecordDto::class)]
final class ContainerRequest extends AbstractPaginatedRequest
{
}
