<?php

declare(strict_types=1);

namespace ApiSutra\Diagnostics;

/** Идентичность одного запуска и его место в дереве операций. */
final readonly class ExecutionTrace
{
    public function __construct(
        public string $traceId,
        public string $executionId,
        public ?string $parentExecutionId = null,
    ) {
    }

    public static function create(?string $traceId = null, ?self $parent = null): self
    {
        return new self($traceId ?? $parent->traceId ?? bin2hex(random_bytes(16)), bin2hex(random_bytes(16)), $parent?->executionId);
    }

    /** @return array{trace: string, executionId: string, parentExecutionId: ?string} */
    public function logContext(): array
    {
        return ['trace' => $this->traceId, 'executionId' => $this->executionId, 'parentExecutionId' => $this->parentExecutionId];
    }
}
