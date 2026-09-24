<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Core\AbstractRequest;

#[Post('/items')]
final class CachePostRequest extends AbstractRequest
{
    public function __construct(#[Body] public string $value = 'payload') {}
}
