<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Pagination\AbstractPaginatedRequest;
use ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCPaginationResultDto;
use ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCRoleHistoryItemDto;
use ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCReportEvent;

#[Get('/report/{uuid}/org-judge.json')]
#[Returns(response: ProviderCPaginationResultDto::class)]
#[Pagination(itemsType: ProviderCRoleHistoryItemDto::class)]
final class ProviderCReportJudgeRoleHistoryRequest extends AbstractPaginatedRequest
{
    public function __construct(
        #[Path('uuid')]
        public string $uuid,
        #[Query(name: 'token')]
        public string $token,
        #[Query(name: 'event')]
        public ProviderCReportEvent $event = ProviderCReportEvent::RoleHistory,
        #[Query]
        public ?int $page = null,
        #[Query]
        public ?int $rows = null,
    ) {}
}
