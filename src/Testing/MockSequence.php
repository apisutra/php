<?php

declare(strict_types=1);

namespace ApiSutra\Testing;

final class MockSequence
{
    /**
     * @var array<int, MockResponse>
     */
    private array $responses;
    private int $index = 0;

    /**
     * @param array<int, MockResponse> $responses
     */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function next(): MockResponse
    {
        $response = $this->responses[$this->index] ?? end($this->responses);
        $this->index = min($this->index + 1, count($this->responses) - 1);

        return $response instanceof MockResponse ? $response : new MockResponse();
    }
}
