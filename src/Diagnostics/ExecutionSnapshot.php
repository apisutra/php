<?php

declare(strict_types=1);

namespace ApiSutra\Diagnostics;

/** Безопасный итог исполнения без ссылок на запрос, результат, исключение и контейнер. */
final readonly class ExecutionSnapshot
{
    /** @param array<string, scalar|null|list<array<string, scalar|null>>> $data */
    public function __construct(public array $data)
    {
    }

    public function isRoot(): bool
    {
        return ($this->data['parentExecutionId'] ?? null) === null;
    }

    public function durationMs(): float
    {
        return (float) ($this->data['durationMs'] ?? 0);
    }

    public function status(): string
    {
        return (string) ($this->data['status'] ?? 'unknown');
    }
}
