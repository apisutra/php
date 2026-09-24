<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Continuation;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Result\ExecutionResult;
use Throwable;
use ApiSutra\Diagnostics\ExecutionTrace;

final class ContinuationAwaitException extends SdkException
{
    public function __construct(
        string|Message $message,
        public readonly string $reason,
        public readonly int $attempts,
        public readonly ExecutionResult $lastResult,
        ?Throwable $previous = null,
        public readonly ?ExecutionTrace $trace = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        if ($this->localizationOrigin instanceof self) {
            return $this->localizationOrigin->context();
        }
        $context = [
            'reason' => $this->reason,
            'attempts' => $this->attempts,
            'httpStatus' => $this->lastResult->response?->status,
            'traceId' => $this->trace->traceId ?? $this->lastResult->traceId,
            ...($this->trace !== null ? ['executionId' => $this->trace->executionId, 'parentExecutionId' => $this->trace->parentExecutionId] : []),
        ];
        $previous = $this->getPrevious();
        if ($this->reason === 'final_hydration_failed' && $previous instanceof HydrationException) {
            $context['hydration'] = $previous->context();
        }

        return $context;
    }

    /** @return array<string, mixed> */
    public function logContext(): array
    {
        if ($this->localizationOrigin instanceof self) {
            return $this->localizationOrigin->logContext();
        }
        $context = $this->context();
        $previous = $this->getPrevious();
        if ($this->reason === 'final_hydration_failed' && $previous instanceof HydrationException) {
            $context['hydration'] = $previous->logContext();
        }
        return $context;
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->messageDefinition() ?? $this->getMessage(), $this->reason, $this->attempts, $this->lastResult, $this, $this->trace);
    }
}
