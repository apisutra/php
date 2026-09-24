<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\RequestDefaults;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Request\RequestUnmappedTarget;

#[Post('/defaults')]
#[RequestDefaults(unmapped: RequestUnmappedTarget::Convention)]
final class RequestDefaultsPostConventionRequest extends AbstractRequest
{
    public function __construct(
        public string $plain,
    ) {}
}
