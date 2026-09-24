<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Auth\OAuth2;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/resource')]
final class ResourceRequest extends AbstractRequest
{
}
