<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;

#[Post('/json')]
final class JsonPayloadRequest extends AbstractRequest
{
    public function __construct(#[BodyRoot] public mixed $payload, #[Query] public string $query = 'fixture-query') {}
}
