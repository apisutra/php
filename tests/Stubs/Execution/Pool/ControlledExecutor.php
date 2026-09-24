<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Execution\Pool;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;
use ApiSutra\Pipeline\Diagnostics\ExecutionScope;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Override;

/** Барьер: исполнение завершается только по явному разрешению теста. */
final class ControlledExecutor implements ClientExecutorInterface
{
    public int $issued = 0;
    public int $completed = 0;
    public int $cancelled = 0;
    /** @var array<int, array{promise: Promise, request: RequestInterface, role: RequestRole}> */
    private array $pending = [];

    public function __construct(private readonly ClientExecutorInterface $inner)
    {
    }

    #[Override]
    public function execute(RequestInterface $request, RequestRole $role = RequestRole::Root, ?PipelineContext $parent = null, ?ExecutionTrace $parentTrace = null, ?ClientExecutorInterface $dispatcher = null): ExecutionResult
    {
        return $this->inner->execute($request, $role, $parent, $parentTrace, $dispatcher);
    }

    #[Override]
    public function executeAsync(RequestInterface $request, RequestRole $role = RequestRole::Root, ?PipelineContext $parent = null, ?ExecutionTrace $parentTrace = null, ?ClientExecutorInterface $dispatcher = null): PromiseInterface
    {
        $index = $this->issued++;
        $promise = new Promise(null, function () use ($index): void {
            unset($this->pending[$index]);
            $this->cancelled++;
        });
        $this->pending[$index] = ['promise' => $promise, 'request' => $request, 'role' => $role];
        return $promise;
    }

    public function complete(int $index, ?ExecutionResult $result = null): void
    {
        $entry = $this->pending[$index];
        unset($this->pending[$index]);
        $this->completed++;
        $entry['promise']->resolve($result ?? $this->execute($entry['request'], $entry['role']));
        (new GuzzlePromiseBridge())->pump();
    }

    #[Override]
    public function createScope(?string $requestClass = null, ?ExecutionTrace $parent = null, ?string $traceId = null): ExecutionScope
    {
        return $this->inner->createScope($requestClass, $parent, $traceId);
    }
}
