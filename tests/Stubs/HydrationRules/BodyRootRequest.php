<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Core\AbstractRequest;

#[Post('/wire')]
final class BodyRootRequest extends AbstractRequest
{
    public function __construct(#[BodyRoot] public mixed $payload)
    {
    }
}
