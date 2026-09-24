<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Transport;

use ApiSutra\Localization\Message;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;

/** @internal Данные одного исполнения; последнее чтение логируется только вне допуска. */
final class CooldownDiagnostics
{
    public int $reads = 0;
    public int $publications = 0;
    public int $durationMs = 0;
    /** @var array{operation: string, duration_ms: int, outcome: string}|null */
    private ?array $pending = null;

    public function record(string $operation, int $durationMs, string $outcome, bool $performed = true): void
    {
        if ($performed) {
            if ($operation === 'read') {
                $this->reads++;
            } else {
                $this->publications++;
            }
        }
        $this->durationMs += $durationMs;
        $this->pending = ['operation' => $operation, 'duration_ms' => $durationMs, 'outcome' => $outcome];
    }

    public function flush(AuditLogger $logger, PipelineContext $context): void
    {
        $event = $this->pending;
        $this->pending = null;
        if ($event !== null) {
            $logger->log(LogLevel::DEBUG, new Message('rate_limit.cooldown_storage'), $context->trace->logContext() + $event);
        }
    }
}
