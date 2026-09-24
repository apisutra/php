<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Core\AbstractRequest;

#[Get('/wire')]
final class QueryCastRequest extends AbstractRequest
{
    public function __construct(#[Query] #[Cast(OpaqueCast::class)] public mixed $payload)
    {
    }
}
