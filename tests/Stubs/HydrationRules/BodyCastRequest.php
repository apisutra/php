<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Core\AbstractRequest;

#[Post('/wire')]
final class BodyCastRequest extends AbstractRequest
{
    public function __construct(#[Body] #[Cast(OpaqueCast::class)] public mixed $payload)
    {
    }
}
