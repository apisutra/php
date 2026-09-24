<?php

declare(strict_types=1);

namespace Example\OAuth2;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/reports')]
final class ResourceRequest extends AbstractRequest
{
}
