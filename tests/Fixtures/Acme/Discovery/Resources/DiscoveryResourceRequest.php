<?php

declare(strict_types=1);

namespace Acme\Discovery\Resources;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/resource')]
final class DiscoveryResourceRequest extends AbstractRequest
{
}
