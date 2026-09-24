<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Casts\UppercaseCast;

#[Post('/cast-body')]
final class CastBodyRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        #[Cast(UppercaseCast::class)]
        public string $payload,
    ) {}
}
