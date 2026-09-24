<!-- languages --> <a href="execution.md">English</a> · <a href="../../ru/development/execution.md">Русский</a> <!-- /languages -->
# Request execution <a id="section-1"></a>

This document describes executor selection and relationships between individual sends.
Internal stages of a single send are in the [pipeline](pipeline.md); the overall map
is in [architecture](architecture.md).

## Entry points and runtime options <a id="section-2"></a>

[AbstractRequest](../../../src/Core/AbstractRequest.php) stores the declaration.
[RequestExecution](../../../src/Request/RequestExecution.php) carries an immutable snapshot
of RequestOptions and PaginationOptions; the entire wrapper reaches the executor.

[AbstractClient](../../../src/Core/AbstractClient.php) is the public delivery facade.
It calls `execution()`, then applies throwOnErrors and builds a ResultHandle bound to
the original request and client. [ClientExecutor](../../../src/Execution/ClientExecutor.php)
creates a local ExecutionEnvelope and context, opens its scope, computes the result,
extracts missing metadata, localizes, checks the final budget, and finishes exactly once.
Public exception selection is outside the computation catch.

## Flow selection <a id="section-3"></a>

| Scenario | Coordinator | Next step |
| --- | --- | --- |
| Single | ClientExecutor | Pipeline.run(open context) |
| Client pagination | ClientExecutor → PaginationTraversal | Single page executions |
| Direct collection / iterator | Paginator → ClientExecutor / PaginationTraversal | Canonical root budget / lazy scope |
| Composite / DependsOn | PipelineCompositeHandler → CompositeFlow | Canonical children, aggregation / main stages |
| Batch | BatchExecutor → BatchContext | Sequential/parallel canonical children |
| Pool | PoolExecutor → ConcurrentExecution | Bounded queue and separate callbacks |
| Continuation | ContinuationService | Readiness decisions, Single polls, final hydration |

All child requests go through ClientExecutorInterface. A context carries the outer
`executor` dispatcher per call, including nested auth. No path depends on the concrete
client class, exception factory, public send override, or a recovered Throwable/result pair.

## Single execution and pagination <a id="section-4"></a>

ClientExecutor initializes the budget before selecting Single or pagination. Both collecting
entry points use it; Paginator only delivers the final aggregate. In Pagination/Traversal,
PaginationProgress owns bounds/guards; ConcurrentPageLoader feeds the existing ConcurrentExecution
after an async bootstrap; PaginationResultAccumulator sorts pages and builds items once.
PaginationFailureFactory attaches traversal identity and client error policies to guards and exceptions;
classification stays in ExecutionErrorFactory and public delivery stays outside the traversal.
Each page forces Single and receives the actual parent context, inheriting the budget and trace.
ResponseHydrator still transforms one page. The lazy iterator has its own scope, keeps fresh
per-page client budgets and does not collect results. [Contract](../reference/execution/pagination.md).

## Composite and dependencies <a id="section-5"></a>

[PipelineCompositeHandler](../../../src/Pipeline/Flow/PipelineCompositeHandler.php)
selects a branch after validation, before preparing the main HTTP request.
[CompositeFlow](../../../src/Pipeline/Execution/CompositeFlow.php) connects it to batch executors:

- `CompositeExecutor` sends `requests()` with role `Nested`. Then `aggregate()` builds
  a value from `ResultCollection`; when a response type is declared, the client's
  hydrator hydrates it. The result contains child executions in `nested`. The
  aggregating request has no separate HTTP send.
- `DependsOnExecutor` sends `dependencies()` with role `Dependency`.
  `processDependencies()` applies their data. `CompositeFlow` then returns control
  to the current Pipeline for the main send. Context, budget, and executionId are
  preserved; dependencies are available in nested.

There is no restart of the main operation or repeated validation. `FailStrategy`
determines whether execution can continue after child errors; the public contract
is in [request composition](../reference/request/composition.md).

## Batch and pool <a id="section-6"></a>

BatchExecutor normalizes instances, callables, and class names; both strategies use
BatchContext's canonical port. FailStrategy stops new work or continues independently
of public throwOnErrors. PoolExecutor routes callbacks by child status.

ConcurrentExecution drains issued promises before returning or rethrowing a callback/
foreign-port failure. It stops new work after FailAll/stopOnFailure; a callback failure
also disables later callbacks. Results are restored to input order before aggregate
cause selection. Public `send()` delivers the final aggregate outside computation.
[Batch/pool contract](../reference/execution/batch-pool.md).

PoolRequestSource owns positional validation and preparation. The eager send path materializes it; consume advances it only when a slot is free. ConcurrentExecution.run retains an active/ready window and returns an internal ExecutionOutcome. Its record callback runs for canonical results even when draining after a crash; user delivery stops on a crash. collect supplies an array collector, while PoolConsumptionState keeps counters and at most one failed result. PoolSummary extends ResultSummary with traversal fields. Sync consumption with one slot stays outside AsyncRuntime. [Consumption contract](../reference/execution/pool-consumption.md).

## Promise API and provider mode <a id="section-7"></a>

ClientExecutor.executeAsync runs the canonical pipeline in a Fiber, fulfilled even
for FAILED. Public sendAsync applies throwOnErrors afterward. An internal SchedulerInterface
isolates Revolt; GuzzlePromiseBridge advances the promise queue and awaits through
suspensions. GuzzleAsyncDriver polls active cURL transfers with select_timeout 0 and
removes its timer when idle. SDK never runs or replaces the host loop.

The concrete scheduler defaults are selected in the constructors of AsyncRuntime
and GuzzlePromiseBridge. Replacing the backend means changing these internal defaults
and verifying the scheduler contract; client APIs expose no scheduler selection.
Injecting a scheduler into one internal component does not reconfigure the entire SDK.
Promise queue scheduling is coalesced per bridge, only when there is queued work.

Request context is a stack per Fiber; DTO traversal state is isolated too. The
pipeline still owns retry/auth/budget rules. ContinuationMode describes provider
readiness independently of transport concurrency. [Contract](../reference/execution/transport.md).

## Awaiting continuation <a id="section-8"></a>

[ContinuationService](../../../src/Continuation/ContinuationService.php) receives the
client and its hydrator. `ResultHandle::await()` passes the starting `ExecutionResult`,
original request, and await options; entry with an already known token is also possible.

The service builds `ContinuationContext` and selects a readiness resolver. Depending
on the mode, it evaluates the starting result or proceeds directly to polling. The
resolver returns Pending, Ready, or Failed; successful DTO construction is not a
readiness test. Poll requests are sent through the client and use the ordinary pipeline.

After Ready, the service hydrates the selected payload if a final type is specified.
`ContinuationOutcome` retains the pre-hydration payload, value, last result, number
of evaluated responses, and await trace and audit. A poll gets a child executionId
in the starting trace without inheriting the completed starting HTTP budget. The
handle retains the outcome: repeated `await()` returns the value; `awaitAs()` with
a different type uses the same payload without more polling.

Final hydration errors end waiting; they do not become Pending. When changing this
branch, check the [readiness criterion](../reference/execution/continuation-state.md)
and [error delivery and await limits](../reference/execution/continuation-await.md).

## Child execution context <a id="section-9"></a>

`parent` carries a dependent request's context, trace, and budget. `parentTrace` alone
creates correlation without a shared HTTP deadline. Contradictory parent/parentTrace
is a configuration error. Pages and polls inherit trace only; auth shares the parent's
remaining budget. Client-created logical scopes use the same clock and logger through
ClientExecutorInterface.createScope, including client decorators.

`sendInContext()` remains a public facade, while internal consumers use the executor
and explicit RequestRole. The [port contract](../reference/extensions/execution.md)
defines dispatcher inheritance; [budgets](../reference/execution/deadlines.md) define timing.

## Where to verify changes <a id="section-10"></a>

| Relationship | Scenarios |
| --- | --- |
| Wrappers and pagination selection | [RequestExecutionChainTest](../../../tests/Unit/Request/RequestExecutionChainTest.php), [RequestResolverTest](../../../tests/Unit/Request/RequestResolverTest.php) |
| Aggregation and dependencies | [CompositeFlowTest](../../../tests/Unit/Execution/CompositeFlowTest.php), [ClientExecutionEntryTest](../../../tests/Unit/Pipeline/ClientExecutionEntryTest.php) |
| Bulk execution | [BatchExecutorTest](../../../tests/Unit/Execution/BatchExecutorTest.php), [PoolExecutorTest](../../../tests/Unit/Execution/PoolExecutorTest.php) |
| Readiness and repeated await | [ContinuationReadinessTest](../../../tests/Unit/Result/ContinuationReadinessTest.php), [ResultHandleContinuationAwaitTest](../../../tests/Unit/Result/ResultHandleContinuationAwaitTest.php) |
| Shared budget | [ExternalDeadlineTest](../../../tests/Unit/Timing/ExternalDeadlineTest.php) |

Canonical boundaries and deferred promises: [CanonicalExecutionTest](../../../tests/Unit/Execution/CanonicalExecutionTest.php), [ExecutionLifecycleTest](../../../tests/Unit/Execution/ExecutionLifecycleTest.php).

Public async returns `ResultPromiseInterface<T>`. AwaitablePromise retains its waiting
mechanism and AsyncTask ownership; the public interface PHPDoc describes type
unwrapping. AbstractClient builds a completed ResultHandle after execution and
localizes delivery rejections; ResultHandle stores no Promise. RequestExecution and
request turn client-resolution errors into rejections. The internal executor keeps
ExecutionResult and Guzzle Promise; see the [public contract](../reference/results/promises.md).
