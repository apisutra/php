<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/events')]
#[Pagination(itemsPath: 'data', itemsType: Event::class)]
final class EventPageRequest extends AbstractPaginatedRequest
{
}
