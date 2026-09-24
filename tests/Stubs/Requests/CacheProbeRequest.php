<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;
use Override;

#[Get('/items')]
final class CacheProbeRequest extends AbstractRequest
{
    /** @param list<int> $ids */
    public function __construct(
        private readonly string $endpoint = '/items',
        #[Query] public array $ids = [],
    ) {}

    #[Override]
    protected function resolveEndpoint(): ?string
    {
        return $this->endpoint;
    }
}
