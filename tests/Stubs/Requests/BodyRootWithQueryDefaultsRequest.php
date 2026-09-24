<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Patch;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Attributes\Request\RequestDefaults;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Request\RequestUnmappedTarget;

#[Patch('/body-root/query-defaults')]
#[RequestDefaults(unmapped: RequestUnmappedTarget::Query)]
final class BodyRootWithQueryDefaultsRequest extends AbstractRequest
{
    /**
     * @param array<int, array<string, mixed>> $operations
     */
    public function __construct(
        #[BodyRoot]
        public array $operations,
        public string $plain,
    ) {}
}
