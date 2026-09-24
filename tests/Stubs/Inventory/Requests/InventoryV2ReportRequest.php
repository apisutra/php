<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Inventory\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Core\AbstractRequest;

#[Get('/inventory/v2/reports/{reportId}')]
final class InventoryV2ReportRequest extends AbstractRequest
{
    public function __construct(
        #[Path('reportId')]
        public string $reportId,
    ) {}
}
