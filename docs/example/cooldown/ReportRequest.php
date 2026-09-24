<?php

declare(strict_types=1);

namespace Examples\Cooldown;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/reports')]
final class ReportRequest extends AbstractRequest
{
}
