<?php

declare(strict_types=1);

namespace Acme\Fallback\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/fallback')]
final class FallbackRequest extends AbstractRequest
{
}
