<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/report')]
#[Returns(ReportDto::class, unwrap: 'data')]
final class ReportRequest extends AbstractRequest
{
}
