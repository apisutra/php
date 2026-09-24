<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Execution;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Pipeline\Diagnostics\ExecutionScope;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Pipeline\PipelineContext;
use Closure;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Throwable;
use ApiSutra\Execution\Async\RevoltScheduler;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;

/** Управляемая очередь цикла: порядок завершения можно обратить. */
final class DeferredExecutor implements ClientExecutorInterface
{
    private bool $scheduled = false;
    public int $issued = 0;
    public int $completed = 0;
    public ?Throwable $failure = null;
    public bool $invalidResult = false;
    public bool $invalidRejection = false;
    /** @var list<ExecutionResult> */
    public array $overrides = [];
    /** @var list<Closure(): void> */
    private array $pending = [];

    public function __construct(private ClientExecutorInterface $inner, private bool $reverse = false)
    {
    }

    public function execute(RequestInterface $request, RequestRole $role = RequestRole::Root, ?PipelineContext $parent = null, ?ExecutionTrace $parentTrace = null, ?ClientExecutorInterface $dispatcher = null): ExecutionResult
    {
        return $this->inner->execute($request, $role, $parent, $parentTrace, $dispatcher ?? $this);
    }

    public function executeAsync(RequestInterface $request, RequestRole $role = RequestRole::Root, ?PipelineContext $parent = null, ?ExecutionTrace $parentTrace = null, ?ClientExecutorInterface $dispatcher = null): PromiseInterface
    {
        $index = $this->issued++;
        $promise = new Promise();
        if (!$this->scheduled) {
            $this->scheduled = true;
            (new RevoltScheduler())->queue(function (): void {
                $this->scheduled = false;
                $pending = $this->pending;
                $this->pending = [];
                foreach ($this->reverse ? array_reverse($pending) : $pending as $complete) {
                    $complete();
                }
                (new GuzzlePromiseBridge())->pump();
            });
        }
        $this->pending[] = function () use ($promise, $request, $role, $parent, $parentTrace, $dispatcher, $index): void {
            if ($this->invalidResult || $this->invalidRejection) {
                $this->completed++;
                $this->invalidRejection ? $promise->reject('invalid') : $promise->resolve('invalid');
                return;
            }
            if ($this->failure !== null) {
                $this->completed++;
                $promise->reject($this->failure);
                return;
            }
            $result = $this->overrides[$index] ?? $this->execute($request, $role, $parent, $parentTrace, $dispatcher);
            $this->completed++;
            $promise->resolve($result);
        };
        return $promise;
    }

    public function createScope(?string $requestClass = null, ?ExecutionTrace $parent = null, ?string $traceId = null): ExecutionScope
    {
        return $this->inner->createScope($requestClass, $parent, $traceId);
    }
}
