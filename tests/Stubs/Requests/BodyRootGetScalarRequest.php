<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;

#[Get('/body-root/scalar')]
final class BodyRootGetScalarRequest extends AbstractRequest
{
    public function __construct(
        #[Query('q')]
        public string $query,
        #[BodyRoot]
        public string $payload,
    ) {}
}
