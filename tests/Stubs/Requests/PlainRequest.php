<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;

#[Get('/plain')]
final class PlainRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $query,
    ) {}
}
