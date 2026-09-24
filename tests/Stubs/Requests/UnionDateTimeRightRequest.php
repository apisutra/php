<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;
use DateTimeImmutable;

#[Post('/union-date-right')]
final class UnionDateTimeRightRequest extends AbstractRequest
{
    public function __construct(
        #[Query('created_at')]
        public string|DateTimeImmutable $createdAt,
        #[Body('payload.created_at')]
        public string|DateTimeImmutable $payloadCreatedAt,
    ) {}
}
