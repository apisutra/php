<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Request\Query;
use ApiSutra\Enums\Http\HttpMethod;
use Override;

final class UriQueryRequest extends RetryPolicyRequest
{
    public function __construct(
        #[Query] public mixed $value = null,
        private readonly string $endpoint = '/items',
        HttpMethod $method = HttpMethod::GET,
        #[Query(nullable: true)] public mixed $included = null,
        #[Query(nullable: false)] public mixed $excluded = null,
    ) {
        parent::__construct($method);
    }

    #[Override]
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }
}
