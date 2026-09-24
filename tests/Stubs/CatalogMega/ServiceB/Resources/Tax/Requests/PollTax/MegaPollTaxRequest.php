<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\PollTax;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;

#[Get('/mega/serviceB/tax/poll')]
final class MegaPollTaxRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $taskId,
    ) {}
}
