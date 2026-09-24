<?php

declare(strict_types=1);

namespace ApiSutra\Testing;

use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;

readonly class MockResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public array|string $data = [],
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    public static function make(array|string $data = [], int $status = 200, array $headers = []): self
    {
        return new self($data, $status, $headers);
    }

    public static function success(array $data = []): self
    {
        return new self($data, 200, ['Content-Type' => 'application/json']);
    }

    /** @param array<string, string> $headers */
    public static function file(string $path, int $status = 200, array $headers = []): self
    {
        return new MockFileResponse($path, $status, $headers);
    }

    public static function notFound(): self
    {
        return new self(['message' => 'Not Found'], 404, ['Content-Type' => 'application/json']);
    }

    public static function serverError(): self
    {
        return new self(['message' => 'Server Error'], 500, ['Content-Type' => 'application/json']);
    }

    public static function rateLimited(int $retryAfter = 60): self
    {
        return new self(
            ['message' => 'Rate Limited'],
            429,
            ['Retry-After' => (string) $retryAfter],
        );
    }

    /**
     * @param array<int, MockResponse> $responses
     */
    public static function sequence(array $responses): MockSequence
    {
        return new MockSequence($responses);
    }

    public function toProviderResponse(PreparedRequest $request): ProviderResponse
    {
        $body = is_string($this->data)
            ? $this->data
            : json_encode($this->data, JSON_UNESCAPED_UNICODE);

        $headers = $this->headers;
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        return new ProviderResponse(
            status: $this->status,
            headers: $this->normalizeHeaders($headers),
            body: (string) $body,
            request: $request,
            duration: 0,
        );
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, array<int, string>>
     */
    private function normalizeHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            $result[$name] = [$value];
        }

        return $result;
    }
}
