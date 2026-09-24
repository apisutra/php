<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;
use DateTimeImmutable;

#[Post('/date-time')]
final class DateTimeSerializationRequest extends AbstractRequest
{
    public function __construct(
        #[Query('created_at')]
        public DateTimeImmutable $createdAt,
        #[Body('payload.created_at')]
        public DateTimeImmutable $payloadCreatedAt,
    ) {}
}
