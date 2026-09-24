<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Transport;

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\ControlFlow\ExecutionCancelledException;
use ApiSutra\Exceptions\RateLimiting\CooldownBackendException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\RateLimiting\Cooldown\Backends\LocalCooldownBackend;
use ApiSutra\RateLimiting\Cooldown\CooldownBackendInterface;
use ApiSutra\RateLimiting\Cooldown\CooldownUpdate;
use ApiSutra\VO\Pipeline\PipelineContext;
use Closure;
use Throwable;

/** @internal Граница I/O, бюджета и ошибок; не хранит состояние отдельных исполнений. */
final readonly class CooldownStore
{
    public function __construct(private CooldownBackendInterface $backend, private AuditLogger $logger)
    {
    }

    public function remainingMs(string $key, PipelineContext $context): int
    {
        return $this->perform('read', $context, function (?int $timeout) use ($key): int {
            $remaining = $this->backend->remainingMs($key, $timeout);
            if ($remaining < 0) {
                throw new CooldownBackendException('cooldown_read');
            }
            return $remaining;
        });
    }

    public function extend(string $key, int $delayMs, int $receivedMs, PipelineContext $context): ?CooldownUpdate
    {
        $remaining = $delayMs - max(0, $context->budget->clock->monotonicMs() - $receivedMs);
        if ($remaining <= 0) {
            return null;
        }
        return $this->perform('publish', $context, function (?int $timeout) use ($key, $remaining): CooldownUpdate {
            $update = $this->backend->extend($key, $remaining, $timeout);
            if ($update->remainingMs < 0 || ($update->extended && $update->remainingMs === 0)) {
                throw new CooldownBackendException('cooldown_publish');
            }
            return $update;
        }, $this->backend instanceof LocalCooldownBackend);
    }

    public function flush(PipelineContext $context): void
    {
        $context->cooldownDiagnostics?->flush($this->logger, $context);
    }

    /**
     * @template T
     * @param Closure(?int): T $operation
     * @return T
     */
    private function perform(string $name, PipelineContext $context, Closure $operation, bool $immediateLocalPublish = false): mixed
    {
        $stage = 'cooldown_' . $name;
        $budget = $context->budget;
        $started = $budget->clock->monotonicMs();
        $outcome = 'success';
        $performed = false;
        try {
            if (!$immediateLocalPublish) {
                $budget->check($stage);
            }
            $performed = true;
            $value = $operation($immediateLocalPublish ? null : $budget->remainingMs());
            if (!$immediateLocalPublish) {
                $budget->check($stage);
            }
            return $value;
        } catch (Throwable $exception) {
            $outcome = $performed ? 'failed' : 'skipped';
            // Истечение общего срока и отмена важнее собственного I/O cap backend.
            if (!$immediateLocalPublish) {
                $budget->check($stage, $exception);
            }
            if ($exception instanceof ConfigurationException || $exception instanceof ExecutionDeadlineException || $exception instanceof ExecutionCancelledException) {
                throw $exception;
            }
            throw new CooldownBackendException($stage, $exception, $context->response ?? $context->lastResponse);
        } finally {
            $duration = max(0, $budget->clock->monotonicMs() - $started);
            if ($name === 'publish') {
                $this->flush($context);
            }
            ($context->cooldownDiagnostics ??= new CooldownDiagnostics())->record($name, $duration, $outcome, $performed);
        }
    }
}
