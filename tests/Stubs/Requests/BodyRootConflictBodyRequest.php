<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Patch;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Core\AbstractRequest;

#[Patch('/body-root/conflict/body')]
final class BodyRootConflictBodyRequest extends AbstractRequest
{
    /**
     * @param array<int, array<string, mixed>> $operations
     */
    public function __construct(
        #[BodyRoot]
        public array $operations,
        #[Body]
        public string $extra,
    ) {}
}
