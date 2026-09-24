<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\RequestOneOf;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Request\OneOfMode;

#[Post('/contract/dot-root-invalid')]
#[RequestOneOf(
    name: 'dot_root_invalid',
    variants: [
        'alpha' => ['type.code'],
    ],
    mode: OneOfMode::ExactlyOne,
)]
final class OneOfInvalidDotRootRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public string $type,
    ) {}
}
