<?php

declare(strict_types=1);

namespace ApiSutra\Execution;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Promise\RejectionException;
use Throwable;
use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;

/** @internal Отличает нарушение порта от первичного сбоя стадии внутри Pipeline. */
final class ExecutionDispatch
{
    public static function execute(ClientExecutorInterface $executor, RequestInterface $request, RequestRole $role, ?PipelineContext $parent = null, ?ExecutionTrace $parentTrace = null): ExecutionResult
    {
        try {
            return $executor->execute($request, $role, $parent, $parentTrace, $executor);
        } catch (Throwable $exception) {
            throw ($exception instanceof ExecutorContractViolation || $exception instanceof AdmissionRefused) ? $exception : new ExecutorContractViolation($exception);
        }
    }

    /** @return PromiseInterface */
    public static function executeAsync(ClientExecutorInterface $executor, RequestInterface $request, RequestRole $role, ?PipelineContext $parent = null, ?ExecutionTrace $parentTrace = null): PromiseInterface
    {
        try {
            return $executor->executeAsync($request, $role, $parent, $parentTrace, $executor)
                ->then(static fn (mixed $result): ExecutionResult => $result)
                ->otherwise(static function (mixed $reason): never {
                    $exception = $reason instanceof Throwable ? $reason : new RejectionException($reason);
                    throw ($exception instanceof ExecutorContractViolation || $exception instanceof AdmissionRefused) ? $exception : new ExecutorContractViolation($exception);
                });
        } catch (Throwable $exception) {
            return new RejectedPromise(($exception instanceof ExecutorContractViolation || $exception instanceof AdmissionRefused) ? $exception : new ExecutorContractViolation($exception));
        }
    }
}
