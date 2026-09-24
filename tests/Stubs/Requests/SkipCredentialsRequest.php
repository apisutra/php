<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\SkipCredentialsEnrichment;
use ApiSutra\Core\AbstractRequest;

#[Post('/credentials/skip')]
#[SkipCredentialsEnrichment]
final class SkipCredentialsRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public ?string $plain = null,
    ) {}
}
