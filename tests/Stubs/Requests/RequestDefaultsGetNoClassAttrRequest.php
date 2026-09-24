<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/defaults')]
final class RequestDefaultsGetNoClassAttrRequest extends AbstractRequest
{
    public function __construct(
        public string $plain,
    ) {}
}
