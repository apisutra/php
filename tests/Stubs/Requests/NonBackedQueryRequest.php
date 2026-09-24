<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Enums\NonBackedStatus;

#[Get('/enum-non-backed')]
final class NonBackedQueryRequest extends AbstractRequest
{
    /**
     * @param array<int, NonBackedStatus> $statuses
     */
    public function __construct(
        #[Query('status_list')]
        public array $statuses,
    ) {}
}
