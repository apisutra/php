<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RelativeTypes;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;

#[Post('/node')]
final class SelfRequest extends AbstractRequest
{
    public function __construct(#[Query] public ?self $query = null, #[Body] public ?self $body = null)
    {
    }
}
