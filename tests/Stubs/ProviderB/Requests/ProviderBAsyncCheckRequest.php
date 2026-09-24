<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderB\Requests;

use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\ProviderB\Dto\ProviderBAsyncResponseDto;

#[Post('/provider-b/async-check')]
#[Returns(ProviderBAsyncResponseDto::class)]
final class ProviderBAsyncCheckRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public string $subjectId,
        #[Body]
        public ?string $profile = null,
    ) {}
}
