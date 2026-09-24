<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\ProfileDrivenOutputDto;

#[Post('/profile-driven-dto-body')]
final class ProfileDrivenDtoBodyRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public ProfileDrivenOutputDto $payload,
    ) {}
}
