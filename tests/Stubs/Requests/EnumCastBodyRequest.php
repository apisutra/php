<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Casts\EnumToStringCast;
use ApiSutra\Tests\Stubs\Enums\TitleStatus;

#[Post('/enum-cast-body')]
final class EnumCastBodyRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        #[Cast(EnumToStringCast::class)]
        public TitleStatus $status,
    ) {}
}
