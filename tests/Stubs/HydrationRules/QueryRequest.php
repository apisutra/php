<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;

#[Get('/wire')]
final class QueryRequest extends AbstractRequest
{
    public function __construct(#[Query] public mixed $payload)
    {
    }
}
