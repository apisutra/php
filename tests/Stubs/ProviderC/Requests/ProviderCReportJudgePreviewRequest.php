<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCReportResponseDto;
use ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCReportEvent;

#[Get('/report/{uuid}/org-judge.json')]
#[Returns(ProviderCReportResponseDto::class)]
final class ProviderCReportJudgePreviewRequest extends AbstractRequest
{
    public function __construct(
        #[Path('uuid')]
        public string $uuid,
        #[Query(name: 'token')]
        public string $token,
        #[Query(name: 'event')]
        public ProviderCReportEvent $event = ProviderCReportEvent::RolePreview,
    ) {}
}
