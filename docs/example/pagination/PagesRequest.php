<?php

declare(strict_types=1);

namespace Examples\Pagination;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/pages')]
final class PagesRequest extends AbstractPaginatedRequest
{
}
