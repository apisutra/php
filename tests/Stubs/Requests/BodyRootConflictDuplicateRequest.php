<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Patch;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Core\AbstractRequest;

#[Patch('/body-root/conflict/duplicate')]
final class BodyRootConflictDuplicateRequest extends AbstractRequest
{
    public function __construct(
        #[BodyRoot]
        public string $first,
        #[BodyRoot]
        public string $second,
    ) {}
}
