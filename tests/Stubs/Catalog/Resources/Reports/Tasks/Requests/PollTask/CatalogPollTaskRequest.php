<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\PollTask;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;

#[Get('/catalog/reports/tasks/poll')]
final class CatalogPollTaskRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $taskId,
    ) {}
}
