<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/catalog/7')]
#[Returns(CatalogItemDto::class, unwrap: 'data')]
final class GetCatalogItemRequest extends AbstractRequest
{
}
