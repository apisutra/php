<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Casts\IntegerCast;
use ApiSutra\Core\AbstractRequest;

#[Post('/integer')]
final class IntegerCastRequest extends AbstractRequest
{
    public function __construct(#[Body, Cast(IntegerCast::class)] public mixed $id) {}
}
