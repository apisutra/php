<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Core\AbstractRequest;

#[Post('/wire')]
final class BodyRootCastRequest extends AbstractRequest
{
    public function __construct(#[BodyRoot] #[Cast(OpaqueCast::class)] public mixed $payload)
    {
    }
}
