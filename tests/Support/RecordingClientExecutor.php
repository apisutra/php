<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Pipeline\Diagnostics\ExecutionScope;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Timing\SystemClock;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Pipeline\PipelineContext;

final class RecordingClientExecutor implements ClientExecutorInterface
{
    public int $calls = 0;
    public ?RequestInterface $lastRequest = null;

    public function __construct(
        private mixed $data = null,
        private ResultStatus $status = ResultStatus::SUCCESS,
    ) {}

    public function execute(
        RequestInterface $request,
        RequestRole $role = RequestRole::Root,
        ?PipelineContext $parent = null,
        ?ExecutionTrace $parentTrace = null,
        ?ClientExecutorInterface $dispatcher = null,
    ): ExecutionResult {
        $this->calls++;
        $this->lastRequest = $request;

        return new ExecutionResult(
            data: $this->data,
            status: $this->status,
            errors: new ErrorCollection([]),
        );
    }

    public function executeAsync(RequestInterface $request, RequestRole $role = RequestRole::Root, ?PipelineContext $parent = null, ?ExecutionTrace $parentTrace = null, ?ClientExecutorInterface $dispatcher = null): PromiseInterface
    {
        return new FulfilledPromise($this->execute($request, $role, $parent, $parentTrace, $dispatcher));
    }

    public function createScope(?string $requestClass = null, ?ExecutionTrace $parent = null, ?string $traceId = null): ExecutionScope
    {
        return new ExecutionScope(ExecutionTrace::create($traceId, $parent), new AuditLogger(new ClientConfig()), new SystemClock(), $requestClass);
    }
}
