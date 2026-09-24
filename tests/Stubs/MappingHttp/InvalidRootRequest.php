<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHttp;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Attributes\Request\Ignore;

final class InvalidRootRequest extends RoutingRequest
{
    #[BodyRoot, Ignore, Cast(CountingCast::class, new Argument('root'))]
    public int $root = 0;
}
