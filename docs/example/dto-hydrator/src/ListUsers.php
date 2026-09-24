<?php

declare(strict_types=1);

namespace Example\DtoHydrator;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/users')]
#[Pagination(itemsType: User::class)]
final class ListUsers extends AbstractPaginatedRequest
{
}
