<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;

#[Get('/continuation/mode')]
final class ContinuationModeRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public bool $manual_async = false,
    ) {}
}
