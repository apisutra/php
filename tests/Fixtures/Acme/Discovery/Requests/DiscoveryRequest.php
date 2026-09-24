<?php

declare(strict_types=1);

namespace Acme\Discovery\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/discovery')]
final class DiscoveryRequest extends AbstractRequest
{
}
