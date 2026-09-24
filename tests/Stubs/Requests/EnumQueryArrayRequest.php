<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Enums\TitleStatus;

#[Get('/enum-query')]
final class EnumQueryArrayRequest extends AbstractRequest
{
    /**
     * @param array<int, TitleStatus> $statuses
     */
    public function __construct(
        #[Query('status_list')]
        public array $statuses,
    ) {}
}
