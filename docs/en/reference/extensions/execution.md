<!-- languages --> <a href="execution.md">English</a> · <a href="../../../ru/reference/extensions/execution.md">Русский</a> <!-- /languages -->
# Extending request execution <a id="section-1"></a>

Use [hooks](hooks.md) to change a request or response at a particular stage.
Use `ClientInterface::execution()` when wrapping the whole execution, including
batch, composite, authentication, pagination, and continuation polls. Every client
implements this method and returns `ClientExecutorInterface` from
`ApiSutra\Contracts\Interfaces\Execution`.

## Executor contract <a id="section-2"></a>

Both methods accept `(RequestInterface $request, RequestRole $role = RequestRole::Root,
?PipelineContext $parent = null, ?ExecutionTrace $parentTrace = null,
?ClientExecutorInterface $dispatcher = null)` in this order:

| Method | Return value |
| --- | --- |
| `execute(...)` | Finalized `ExecutionResult`, including `FAILED` |
| `executeAsync(...)` | `PromiseInterface` fulfilled with that same kind of result, including `FAILED` |

The result has completed metadata extraction, localization, deadline checks, and
terminal diagnostics. Ordinary HTTP/DTO/handler failures remain canonical results;
`throwOnErrors` and exception factories are applied by public delivery **after** this
boundary. Do not call `throw()` or public `send()` to implement the executor.

A foreign executor throwing, rejecting, or fulfilling with another type violates
this contract. Its failure propagates without HTTP retry or exception-factory mapping.
Concurrent orchestration stops new work and drains issued promises. A custom executor must progress its pending promises in the SDK event loop; a
blocking Guzzle wait-function alone is insufficient. The built-in executor uses
cooperative HTTP through the [transport contract](../execution/transport.md).

`parent` inherits a dependent request's active budget and context. `parentTrace`
only correlates a new execution; pages and polls use it with their own HTTP budgets.
When both are supplied, `parentTrace` must be the parent's trace. A contradictory
pair throws `ConfigurationException` before HTTP. Request trace overrides keep their
priority. Reusing a request creates another executionId and fresh per-call context.

## Decorator forwarding <a id="section-3"></a>

An executor decorator forwards **all five arguments**, using `dispatcher ?? $this`
for the last one. For example, inside its `execute()` implementation:

```php
return $this->inner->execute($request, $role, $parent, $parentTrace, $dispatcher ?? $this);
```

`executeAsync()` forwards identically and returns the delegated promise. This carries
the outermost decorator into child operations, including auth refresh within a composite
child. Store configuration on the decorator; keep current context/dispatcher local to
the invocation. A `ClientInterface` wrapper delegates `execution()` or exposes such a
decorator. An `AbstractClient` subclass can override `execution()` and wrap
`parent::execution()`; wrap the current executor if the client swaps its transport.

Overriding public `send()`/`sendAsync()` only wraps direct user calls. Internal dispatch
always uses `execution()`, with or without an exception factory. `sendInContext()`
remains a public facade with the same error delivery rules as `send()`.
Its async counterpart, `sendInContextAsync($request, $parent, $role)`, returns
`ResultPromiseInterface<ResultHandle>` and passes the parent `PipelineContext` to
the child execution. Both facades preserve the trace link and inherit the parent
deadline; wait for the async promise before consuming the child result.

## Logical execution scopes <a id="section-4"></a>

`createScope(?string $requestClass = null, ?ExecutionTrace $parent = null,
?string $traceId = null): ExecutionScope` creates a fresh scope in Created state with
the client's clock, logger, and correlation settings. Decorators delegate it unchanged.
A custom orchestration owner calls `start()` and `finish()` exactly once; a stopped
lazy traversal uses `abandon()`. Do not finalize the executor's request scope yourself.

Direct paginator/iterator and await own separate logical scopes. Client-driven
pagination is finalized by its executor. Batch/pool without a parent combine independent
root results. Details: [tracing](../results/observability.md),
[error delivery](../results/exceptions.md).

## Promise waiters and application Fibers <a id="section-5"></a>

Waiting from the main stack advances the loop until the requested SDK Promise
settles. This does not guarantee that every Fiber waiting for that Promise has
resumed: their continuations may still be queued. This applies to both SDK tasks
and Fibers created by the application. Await the consumers' own promises when their
completion matters; `Utils::all()` can combine them.

Calling `Fiber::start()` alone does not keep the event loop running. For application
Fibers, the application must keep advancing its loop until those Fibers finish.
SDK calls work inside an already running Revolt loop through suspensions; they do
not call `EventLoop::run()` or drain unrelated work before returning a result.


Use a [retry delay policy or transport decorator](../execution/retry.md#delay-policy) for backoff
or per-attempt behavior. Sending remains behind the canonical HTTP admission boundary.
