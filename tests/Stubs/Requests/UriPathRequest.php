<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Core\AbstractRequest;

#[Get('/items/{id}')]
final class UriPathRequest extends AbstractRequest
{
    public function __construct(#[Path] public mixed $id = null) {}
}
