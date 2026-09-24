<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Tracing;

use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

final class MemoryLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function __construct(private readonly ?string $failOn = null)
    {
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
        if ($this->failOn === '*' || $this->failOn === (string) $message) {
            throw new RuntimeException('Synthetic logger failure');
        }
    }
}
