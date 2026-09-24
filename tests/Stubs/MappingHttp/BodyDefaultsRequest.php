<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHttp;

use ApiSutra\Attributes\Request\RequestDefaults;
use ApiSutra\Enums\Request\RequestUnmappedTarget;

#[RequestDefaults(RequestUnmappedTarget::Body)]
final class BodyDefaultsRequest extends RoutingRequest
{
}
