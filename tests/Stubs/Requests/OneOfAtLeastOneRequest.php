<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\RequestOneOf;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Request\OneOfMode;

#[Post('/contract/at-least-one')]
#[RequestOneOf(
    name: 'contact_payload',
    variants: [
        'email' => ['email'],
        'phone' => ['phone'],
    ],
    mode: OneOfMode::AtLeastOne,
)]
final class OneOfAtLeastOneRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public ?string $email = null,
        #[Body]
        public ?string $phone = null,
    ) {}
}
