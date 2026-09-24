<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Core\AbstractRequest;

#[Post('/body')]
final class BodyCastRequest extends AbstractRequest
{
    #[Body]
    #[Cast(CreatedCast::class, ['nested' => [new CreatedValue()]])]
    public int $number = 0;
}
