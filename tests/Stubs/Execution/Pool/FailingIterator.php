<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Execution\Pool;

use ApiSutra\Tests\Stubs\Async\ProbeRequest;
use Iterator;
use Override;
use Throwable;

/** @implements Iterator<string, ProbeRequest> */
final class FailingIterator implements Iterator
{
    private int $position = 0;

    public function __construct(private readonly string $method, private readonly Throwable $failure)
    {
    }

    #[Override]
    public function rewind(): void
    {
        $this->check(__FUNCTION__);
        $this->position = 0;
    }

    #[Override]
    public function valid(): bool
    {
        $this->check(__FUNCTION__);
        return $this->position < 3;
    }

    #[Override]
    public function current(): ProbeRequest
    {
        $this->check(__FUNCTION__);
        return new ProbeRequest();
    }

    #[Override]
    public function key(): string
    {
        return 'key';
    }

    #[Override]
    public function next(): void
    {
        $this->position++;
        $this->check(__FUNCTION__);
    }

    private function check(string $method): void
    {
        if ($method === $this->method && ($this->position === 1 || $method === 'rewind')) {
            throw $this->failure;
        }
    }
}
