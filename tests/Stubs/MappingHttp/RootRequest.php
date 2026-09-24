<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHttp;

use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Http\HttpMethod;

final readonly class RootRequest implements RequestInterface
{
    /** @param array<string, int> $root */
    public function __construct(
        #[BodyRoot] public array $root = ['id' => 7],
        public int $other = 8,
        private HttpMethod $method = HttpMethod::GET,
        private string $endpoint = '/root',
    ) {
    }

    public function getMethod(): HttpMethod
    {
        return $this->method;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getResponseType(): ?string
    {
        return null;
    }
}
