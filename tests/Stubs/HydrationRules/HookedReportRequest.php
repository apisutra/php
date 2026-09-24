<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/hooked-report')]
#[Returns(ReportDto::class, unwrap: 'data')]
final class HookedReportRequest extends AbstractRequest
{
    protected function beforeHydrate(PipelineContext $context, array $data): array
    {
        $data['data'] = $data['envelope'];
        unset($data['envelope']);
        return $data;
    }
}
