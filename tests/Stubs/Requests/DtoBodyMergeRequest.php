<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\OutputUserDto;

#[Post('/dto-body-merge')]
final class DtoBodyMergeRequest extends AbstractRequest
{
    public function __construct(
        #[Body(nested: 'payload.data')]
        public OutputUserDto $payload,
        #[Body(nested: 'payload.meta.request_id')]
        public string $requestId,
    ) {}
}
