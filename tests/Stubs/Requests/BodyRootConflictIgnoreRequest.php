<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Patch;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Attributes\Request\Ignore;
use ApiSutra\Core\AbstractRequest;

#[Patch('/body-root/conflict/ignore')]
final class BodyRootConflictIgnoreRequest extends AbstractRequest
{
    public function __construct(
        #[Ignore]
        #[BodyRoot]
        public string $payload,
    ) {}
}
