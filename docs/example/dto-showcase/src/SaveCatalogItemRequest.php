<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Core\AbstractRequest;

#[Post('/catalog')]
final class SaveCatalogItemRequest extends AbstractRequest
{
    public function __construct(#[BodyRoot] public CatalogItemDto $item)
    {
    }
}
