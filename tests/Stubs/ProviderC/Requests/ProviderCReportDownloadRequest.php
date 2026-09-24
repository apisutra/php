<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC\Requests;

use ApiSutra\Attributes\Response\Download;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;

#[Get('/report/{uuid}/report.{format}')]
#[Download]
final class ProviderCReportDownloadRequest extends AbstractRequest
{
    public function __construct(
        #[Path('uuid')]
        public string $uuid,
        #[Path('format')]
        public string $format,
        #[Query(name: 'token')]
        public string $token,
        #[Query(name: 'report-name')]
        public string $reportName,
        #[Query(name: 'timeout')]
        public ?string $timeout = null,
    ) {}
}
