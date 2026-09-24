<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/page')]
#[Returns(DefaultsContainer::class)]
#[Pagination(itemsPath: 'data', itemsType: DefaultsDto::class)]
final class PaginatedDefaultsRequest extends AbstractPaginatedRequest
{
}
