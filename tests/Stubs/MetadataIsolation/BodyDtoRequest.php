<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Core\AbstractRequest;

#[Post('/body')]
final class BodyDtoRequest extends AbstractRequest
{
    public function __construct(
        #[BodyRoot]
        public CastDto $payload = new CastDto(),
    ) {
    }
}
