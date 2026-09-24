<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Pagination\AbstractPaginatedRequest;
use ApiSutra\Tests\Stubs\Pagination\AttributeMetaResolver;

#[Get('/meta-attribute')]
#[Pagination(metaResolver: AttributeMetaResolver::class)]
final class AttributeMetaResolverRequest extends AbstractPaginatedRequest
{
}
