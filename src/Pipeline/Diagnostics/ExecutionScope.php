<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Diagnostics;

use ApiSutra\Exceptions\RateLimiting\CooldownBackendException;
use ApiSutra\Exceptions\Request\CooldownException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Exceptions\ControlFlow\ExecutionCancelledException;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Localization\Message;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Audit\DebugInfo;
use ApiSutra\VO\Audit\PipelineEvent;
use Psr\Log\LogLevel;
use ApiSutra\VO\Http\ProviderResponse;
use Throwable;
use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;
use Closure;
use ApiSutra\Execution\ExecutionActivity;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\VO\Http\PreparedRequest;

/** @internal Единственный владелец журнала и финализации конкретного запуска. */
final class ExecutionScope
{
    private ExecutionState $state = ExecutionState::Created;
    private int $startedMs;
    private ?ExecutionResult $result = null;
    private int $attempts = 0;
    private float $httpDurationMs = 0;
    private ?int $httpStartedMs = null;
    private ?string $httpMethod = null;
    private ?string $httpOrigin = null;
    private bool $released = false;
    /** @var Closure(): void|null */
    private ?Closure $releaseActivity = null;
    private bool $includeAttempts = false;
    /** @var list<array<string, scalar|null>> */
    private array $attemptDetails = [];
    /** @var Closure(): void|null */
    private ?Closure $unsubscribeCancellation = null;
    /** @var list<PipelineEvent> */
    public private(set) array $audit = [];

    public function __construct(
        public readonly ExecutionTrace $trace,
        private readonly AuditLogger $logger,
        private readonly ClockInterface $clock,
        private readonly ?string $requestClass = null,
        private readonly RequestRole $role = RequestRole::Root,
        private readonly ?ExecutionObservation $observation = null,
        private readonly ?ExecutionActivity $activity = null,
    ) {
        $this->startedMs = $clock->monotonicMs();
    }

    public function start(): void
    {
        if ($this->state !== ExecutionState::Created) {
            return;
        }
        $this->state = ExecutionState::Running;
        $this->releaseActivity = $this->activity?->acquire();
        $this->observation?->start($this->trace);
        $this->includeAttempts = $this->observation?->includeAttempts() ?? false;
        $this->record(PipelineStage::Started);
        $this->unsubscribeCancellation = AsyncTask::current()?->onCancel(function (bool $resume): void {
            // Promise отменяется сразу; диагностика не должна ждать разворачивания Fiber.
            $this->stop($resume ? PipelineStage::Failed : PipelineStage::Abandoned, 'execution_cancelled');
        });
        $this->logger->log(LogLevel::INFO, new Message('pipeline.request_started'), $this->logContext() + ['event' => 'started']);
    }

    public function duration(): float
    {
        return (float) max(0, $this->clock->monotonicMs() - $this->startedMs);
    }

    /** @param array<string, scalar|null> $context */
    public function record(PipelineStage $stage, array $context = [], ?DebugInfo $payload = null): void
    {
        if ($this->state === ExecutionState::Finalized) {
            return;
        }
        $this->audit[] = new PipelineEvent(
            $stage,
            (float) $this->clock->unixTime(),
            $stage === PipelineStage::Started ? null : $this->duration(),
            $this->requestClass,
            $this->role,
            $payload,
            $this->trace,
            ['event' => $stage->value] + $context,
        );
    }

    /** @param callable(): ProviderResponse $send */
    public function http(callable $send, ?PreparedRequest $request = null): ProviderResponse
    {
        $attempt = ++$this->attempts;
        $this->record(PipelineStage::HttpRequest, ['attempt' => $attempt]);
        if ($this->observation !== null) {
            $this->httpStartedMs = $this->clock->monotonicMs();
            $this->httpMethod = $request?->method->value;
            $this->httpOrigin = $request?->destination?->origin;
        }
        $status = null;
        try {
            $response = $send();
            $status = $response->status;
        } catch (Throwable $exception) {
            $this->record(PipelineStage::HttpResponse, ['attempt' => $attempt, 'exception' => $exception::class]);
            throw $exception;
        } finally {
            if ($this->httpStartedMs !== null) {
                $duration = max(0, $this->clock->monotonicMs() - $this->httpStartedMs);
                $this->httpDurationMs += $duration;
                $this->httpStartedMs = null;
                if ($this->includeAttempts && count($this->attemptDetails) < 32) {
                    $this->attemptDetails[] = ['attempt' => $attempt, 'durationMs' => $duration, 'httpStatus' => $status];
                }
            }
        }
        $this->record(PipelineStage::HttpResponse, ['attempt' => $attempt, 'httpStatus' => $response->status]);
        return $response;
    }

    /** @param array<string, mixed>|Closure(): array<string, mixed> $details */
    public function finish(ExecutionResult $result, array|Closure $details = []): ExecutionResult
    {
        if ($this->result !== null) {
            return $this->result;
        }
        $this->start();
        $stage = $result->isFailed() ? PipelineStage::Failed : PipelineStage::Completed;
        $stopped = $this->state === ExecutionState::Finalized;
        $debug = $result->debug === null ? null : new DebugInfo(
            $result->debug->preparedRequest,
            $result->debug->response,
            $stopped ? $this->audit[array_key_last($this->audit)]->duration : $this->duration(),
            $result->debug->nested,
        );
        if ($stopped) {
            // Поздний результат сохраняет детали ошибки, но не открывает завершённый журнал снова.
            return $this->result = $result->withDiagnostics($this->trace, $this->audit, $debug);
        }
        $this->record($stage, ['status' => $result->status->value], $debug);
        $this->state = ExecutionState::Finalized;
        $this->unsubscribe();
        $this->result = $result->withDiagnostics($this->trace, $this->audit, $debug);
        $error = $result->errors->first();
        $errorContext = $error->context ?? [];
        $reason = $errorContext['reason'] ?? ($result->exception instanceof AdmissionRefused ? $result->exception->reason : $error?->code->value);
        $errorStage = $errorContext['stage'] ?? ($result->exception instanceof AdmissionRefused ? ($result->exception->cause instanceof CooldownException ? 'cooldown' : 'rate_limit') : null);
        $this->observe($result->status->value, is_string($reason) ? $reason : null, is_string($errorStage) ? $errorStage : null);
        $logContext = function () use ($result, $stage, $details): array {
            $details = $details instanceof Closure ? $details() : $details;
            if ($result->exception instanceof HydrationException) {
                $details += $result->exception->logContext() + [
                    'httpStatus' => $result->response?->status, 'traceId' => $this->trace->traceId,
                ];
            }
            if ($result->exception instanceof CooldownException || $result->exception instanceof CooldownBackendException) {
                $details += $result->exception->context();
            } elseif ($result->exception instanceof AdmissionRefused) {
                $details += ['reason' => $result->exception->reason, 'stage' => $result->exception->cause instanceof CooldownException ? 'cooldown' : 'rate_limit'];
            } elseif ($result->exception instanceof ExecutionDeadlineException) {
                $details += ['reason' => 'execution_deadline_exceeded', 'stage' => $result->exception->stage];
            }
            return $this->logContext() + [
                'event' => $stage->value, 'status' => $result->status->value, 'duration_ms' => $this->duration(),
                'code' => $result->errors->first()?->code->value,
                'exception' => $result->exception === null ? null : $result->exception::class,
            ] + $details;
        };
        $this->logger->log(
            $result->isFailed() ? LogLevel::ERROR : LogLevel::INFO,
            new Message($result->isFailed() ? 'pipeline.request_failed' : 'pipeline.request_completed'),
            $logContext,
        );
        return $this->result;
    }

    public function abandon(): void
    {
        $this->stop(PipelineStage::Abandoned, 'iteration_stopped');
    }

    private function stop(PipelineStage $stage, string $reason): void
    {
        if ($this->state !== ExecutionState::Running) {
            return;
        }
        $details = ['reason' => $reason];
        if ($stage === PipelineStage::Failed) {
            $details += [
                'status' => ResultStatus::FAILED->value,
                'code' => ErrorCode::ExecutionError->value,
                'exception' => ExecutionCancelledException::class,
            ];
        }
        $this->record($stage, $details);
        $this->state = ExecutionState::Finalized;
        $this->unsubscribe();
        $this->observe($stage === PipelineStage::Abandoned ? 'abandoned' : 'failed', $reason, null);
        $message = $reason === 'iteration_stopped' ? new Message('pipeline.iteration_abandoned') : new Message('execution.cancelled');
        $this->logger->log($stage === PipelineStage::Failed ? LogLevel::ERROR : LogLevel::INFO, $message, $this->logContext() + [
            'event' => $stage->value, 'duration_ms' => $this->duration(),
        ] + $details);
    }

    private function unsubscribe(): void
    {
        ($this->unsubscribeCancellation)?->__invoke();
        $this->unsubscribeCancellation = null;
    }

    /** Cleanup отделён от терминала: отмена может завершить диагностику раньше Fiber. */
    public function release(): void
    {
        if ($this->released) {
            return;
        }
        $this->released = true;
        if ($this->observation !== null || $this->releaseActivity !== null) {
            $release = function (): void {
                $this->observation?->release($this->trace);
                ($this->releaseActivity)?->__invoke();
                $this->releaseActivity = null;
            };
            $task = AsyncTask::current();
            $task === null ? $release() : $task->onFinish($release);
        }
    }

    private function observe(string $status, ?string $reason, ?string $stage): void
    {
        if ($this->observation === null) {
            return;
        }
        $activeMs = $this->httpStartedMs === null ? 0 : max(0, $this->clock->monotonicMs() - $this->httpStartedMs);
        $data = [
            'traceId' => $this->trace->traceId, 'executionId' => $this->trace->executionId,
            'parentExecutionId' => $this->trace->parentExecutionId, 'operation' => $this->requestClass,
            'role' => $this->role->value, 'status' => $status, 'reason' => $reason, 'stage' => $stage,
            'durationMs' => $this->duration(), 'attemptCount' => $this->attempts,
            'httpDurationMs' => $this->httpDurationMs + $activeMs, 'method' => $this->httpMethod,
            'origin' => $this->httpOrigin, 'clientLabel' => $this->observation->clientLabel,
        ];
        if ($this->includeAttempts) {
            $data['attempts'] = $this->attemptDetails;
            $data['omittedAttempts'] = $this->attempts - count($this->attemptDetails);
        }
        $this->observation->complete($data);
    }

    /** @return array<string, scalar|null> */
    private function logContext(): array
    {
        return $this->trace->logContext() + ['request' => $this->requestClass, 'role' => $this->role->value];
    }
}
