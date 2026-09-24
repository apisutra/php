<?php

declare(strict_types=1);

namespace Example\ClientShowcase\Support;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

// В примере сохраняем журнал в памяти; приложение передаёт свой PSR-3 logger.
final class MemoryLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /** @param array<string, mixed> $context */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }
}
