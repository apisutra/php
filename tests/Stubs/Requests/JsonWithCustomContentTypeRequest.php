<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Core\AbstractRequest;

#[Post('/custom-ct')]
final class JsonWithCustomContentTypeRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public string $data = '{}',
        #[Header('Content-Type')]
        public string $contentType = 'application/xml',
    ) {}
}
