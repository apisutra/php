<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Execution;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Pipeline\Diagnostics\ExecutionScope;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\PromiseInterface;
use Throwable;
use WeakReference;

final class RecordingExecutor implements ClientExecutorInterface
{
    /** @var list<class-string> */
    public array $requests = [];
    /** @var list<WeakReference<ExecutionResult>> */
    public array $results = [];
    public ?Throwable $failure = null;
    public ?string $failureRequest = null;

    public function __construct(private ClientExecutorInterface $inner)
    {
    }

    public function execute(RequestInterface $request, RequestRole $role = RequestRole::Root, ?PipelineContext $parent = null, ?ExecutionTrace $parentTrace = null, ?ClientExecutorInterface $dispatcher = null): ExecutionResult
    {
        $this->requests[] = ($request instanceof RequestExecutionInterface ? $request->getRequest() : $request)::class;
        if ($this->failure !== null && ($this->failureRequest === null || $this->failureRequest === $this->requests[array_key_last($this->requests)])) {
            throw $this->failure;
        }
        $result = $this->inner->execute($request, $role, $parent, $parentTrace, $dispatcher ?? $this);
        $this->results[] = WeakReference::create($result);
        return $result;
    }

    public function executeAsync(RequestInterface $request, RequestRole $role = RequestRole::Root, ?PipelineContext $parent = null, ?ExecutionTrace $parentTrace = null, ?ClientExecutorInterface $dispatcher = null): PromiseInterface
    {
        $this->requests[] = ($request instanceof RequestExecutionInterface ? $request->getRequest() : $request)::class;
        if ($this->failure !== null && ($this->failureRequest === null || $this->failureRequest === $this->requests[array_key_last($this->requests)])) {
            throw $this->failure;
        }
        return $this->inner->executeAsync($request, $role, $parent, $parentTrace, $dispatcher ?? $this)->then(function (ExecutionResult $result): ExecutionResult {
            $this->results[] = WeakReference::create($result);
            return $result;
        });
    }

    public function createScope(?string $requestClass = null, ?ExecutionTrace $parent = null, ?string $traceId = null): ExecutionScope
    {
        return $this->inner->createScope($requestClass, $parent, $traceId);
    }
}
