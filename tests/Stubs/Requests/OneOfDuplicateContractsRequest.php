<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\RequestOneOf;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Request\OneOfMode;

#[Post('/contract/duplicate')]
#[RequestOneOf(
    name: 'dup',
    variants: [
        'alpha' => ['alphaData'],
    ],
    mode: OneOfMode::ExactlyOne,
)]
#[RequestOneOf(
    name: 'dup',
    variants: [
        'beta' => ['betaData'],
    ],
    mode: OneOfMode::ExactlyOne,
)]
final class OneOfDuplicateContractsRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public ?string $alphaData = null,
        #[Body]
        public ?string $betaData = null,
    ) {}
}
