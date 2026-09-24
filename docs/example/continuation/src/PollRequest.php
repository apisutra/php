<?php

declare(strict_types=1);

namespace Example\Continuation;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Core\AbstractRequest;

#[Get('/operations/{token}')]
final class PollRequest extends AbstractRequest
{
    public function __construct(
        #[Path]
        public string $token,
    ) {
    }
}
