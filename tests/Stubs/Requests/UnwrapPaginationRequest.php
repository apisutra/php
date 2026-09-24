<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Pagination\AbstractPaginatedRequest;
use ApiSutra\Tests\Stubs\Dto\PaginationContainerDto;
use ApiSutra\Tests\Stubs\Dto\PaginationItemDto;

#[Get('/wrapped')]
#[Returns(PaginationContainerDto::class, unwrap: 'response')]
#[Pagination(itemsPath: 'response.result', itemsType: PaginationItemDto::class)]
final class UnwrapPaginationRequest extends AbstractPaginatedRequest
{
}
