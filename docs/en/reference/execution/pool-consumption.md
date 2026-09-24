<!-- languages --> <a href="pool-consumption.md">English</a> · <a href="../../../ru/reference/execution/pool-consumption.md">Русский</a> <!-- /languages -->
# Consume a pool without collecting results <a id="section-1"></a>

Use `consume()` for a large or unknown number of independent requests. It reads an
iterable as concurrency slots become available, delivers results to your handlers,
and returns a small `ApiSutra\Result\PoolSummary`. `consumeAsync()` returns `ResultPromiseInterface<PoolSummary>`
fulfilled with the same summary. Await it; neither method provides fire-and-forget.

`consume()` waits for completion while HTTP can run concurrently. With concurrency 1,
outside an SDK async task, it uses synchronous execution and accepts sync-only transports.
`consumeAsync()` still requires an [async-capable transport](transport.md#section-5).
There is no new configuration block or dependency.

## Source and handlers <a id="section-2"></a>

Here `$client` is your SDK client, `$ids` is an iterable from your application,
`$makeRequest($id)` returns a `RequestInterface`, and `$save` / `$recordFailure` are
application callbacks. They receive the request to associate a result with its input.

```php
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Result\ExecutionResult;

$requests = (static function () use ($ids, $makeRequest): Generator {
    foreach ($ids as $id) {
        yield $makeRequest($id);
    }
})();

$summary = $client->pool($requests)
    ->withResponseHandler(function (ExecutionResult $result, RequestInterface $request) use ($save): void {
        $save($result->data, $request);
    })
    ->withExceptionHandler(function (Throwable $error, RequestInterface $request) use ($recordFailure): void {
        $recordFailure($error, $request);
    })
    ->consume();
```

Handlers run in completion order. FAILED goes to the exception handler when supplied;
otherwise it goes to the response handler. SUCCESS/PARTIAL go to the response handler.
Return values, including `false`, do not control execution. Both handlers are optional:
`$client->pool($requests)->consume()` is valid for requests whose effects need only a summary.

For async, use a fresh source and await completion:

```php
$promise = $client->pool($freshRequests, concurrency: 8)
    ->withResponseHandler($onResponse)
    ->consumeAsync();
$summary = $promise->wait();
```

The source is traversed once and not materialized. Arbitrary source keys are accepted;
diagnostic indexes are zero-based input positions, not keys. The SDK does not restart
an exhausted Generator. Create a new source for another run.

Concurrency precedence: last `withConcurrency()` → explicit creation argument →
`PoolConfig::concurrency` → 5. An omitted/null creation argument uses the default;
`withConcurrency()` requires an explicit value and returns a copy. The same rule applies to direct `new PoolExecutor(...)`;
an explicitly supplied PoolConfig overrides the client's pool configuration.
Numbers are clamped to at least 1, as with ordinary pool execution.

A concurrency resolver requires an array or a `Countable` iterable. It is called once
with `(total, 0)` and must return an integer. An unknown size, an invalid return type,
or a resolver exception gives `ConfigurationException` before traversal or HTTP.
Use an integer for generators. Numeric concurrency does not call `count()`;
a failing user `count()` is a source failure with a zero summary.

## Summary and error delivery <a id="section-3"></a>

`PoolSummary` extends the immutable `ResultSummary` counters with traversal information.
It contains no responses, requests, exceptions, or results collection.

| Field | Meaning |
| --- | --- |
| `started` | Elements handed to the executor, including cache hits and failures before HTTP. Not the number of HTTP attempts. |
| `total` | Valid completed ExecutionResult values, including already started work drained after a failure. |
| `successful`, `failed`, `partial` | Result counts; their sum is `total`. |
| `status` | Nonempty all SUCCESS → SUCCESS; nonempty all FAILED → FAILED; otherwise PARTIAL, including an empty source. |
| `firstFailedIndex` | Lowest input position with a FAILED result, regardless of completion order; null when there is none. |
| `terminationReason` | `ApiSutra\Enums\Execution\PoolTerminationReason`: source_exhausted, stop_on_failure, source_failed, handler_failed, factory_failed, or executor_failed. |

`started` can exceed `total` if an executor fails without returning a valid result.
Result status and traversal failure are independent: a source can fail after every
completed request succeeded. `isAllSuccess()`, `isAllFailed()`, and `isAllPartial()`
have the same meaning as on ResultSummary.

With `throwOnErrors: true`, only an overall FAILED triggers the terminal throw.
An exception handler does not suppress it. One success among many failures produces
PARTIAL and returns normally: use handlers and counters to detect individual failures,
not just try/catch. With `throwOnErrors: false`, all provider failures return a summary.

For a terminal FAILED, the [exception factory](../results/exceptions.md) receives
**one actual failed element's ExecutionResult**, selected by the lowest input position.
It does not receive the PoolResult aggregate used by `send()`. A factory that branches
on aggregates may therefore choose a different exception type. The selected exception
is thrown directly; no final summary is attached. A factory returning null keeps the
standard fallback. Exception-handler selection and terminal selection are separate
calls; factory output is not cached.

## Interrupted consumption <a id="section-4"></a>

When `stopOnFailure` is enabled, consumption stops reading and starting elements after
observing FAILED. Set it [for one pool or in PoolConfig](batch-pool.md#section-4).
Already started work finishes, reaches handlers, and contributes to the summary.
The reason is `stop_on_failure`, even if that happened to be the last input element:
the SDK does not read ahead to find out.

A source, invalid element, handler, exception factory, or executor crash stops both
new work and further handlers. Already started work is drained and counted. Then
`ApiSutra\Exceptions\Execution\PoolConsumptionException` exposes `summary` and the
cause through `getPrevious()`. The first crash wins over later failures. A factory
crash preserves its existing ExceptionFactoryException and the original cause in
that chain. `throwOnErrors: false` does not suppress these crashes.

```php
use ApiSutra\Exceptions\Execution\PoolConsumptionException;

try {
    $summary = $pool->consume();
} catch (PoolConsumptionException $error) {
    $summary = $error->summary;
    $cause = $error->getPrevious();
    // Record the interrupted operation in your application.
}
```

Catch your own provider exception type separately for the normal terminal FAILED.
A factory that itself throws causes PoolConsumptionException; an exception it returns
is delivered directly. No extra HTTP retry or recursive factory call follows either.

There is no normal “enough results” signal from a handler in this API. Limit the
source when its size can be determined in advance. Throwing from a handler is an
interruption: some already completed, possibly paid-for responses will not reach
application handlers. HTTP effects and consumed quotas are not rolled back.
Without element deadlines, draining is not guaranteed to finish within a fixed time.

Explicit Promise cancellation and abandonment follow the [async contract](transport.md#section-2):
best-effort cancellation and cleanup, without a guaranteed final summary. The SDK
does not advance a retained Generator to force its finally block; your application
owns its references and resources.

## Memory and application responsibilities <a id="section-5"></a>

The orchestrator retains an active/ready window proportional to concurrency, counters,
and at most one failed result for a possible terminal throw. It does not collect
completed inputs or outputs. This is a bound on retained elements, not bytes: one
response/composite can be large. Source arrays, application callbacks, loggers,
recording transports, and retained exceptions can still accumulate memory.
Dropping SDK references does not close a file stream retained by your handler.

The summary is not a checkpoint or a count of database writes. Track committed IDs
or a contiguous committed prefix in your application; the greatest completed position
is insufficient when results arrive out of order. `firstFailedIndex` is diagnostic only.
SDK callbacks may await other SDK calls, but a blocking database driver or other
synchronous application code still blocks the PHP thread.

For a full result collection use [send/sendAsync](batch-pool.md): both prepare the entire
input before execution. A consume source error can instead occur after earlier HTTP
effects. Batch consumption and pull-style `stream()` iteration are not provided;
stopping and releasing active work would require a separate contract.


For imports that should wait through HTTP 429, see [cooldown and import budgets](cooldown.md#imports).
A long prohibition without a total budget can produce successive local failures; pool does not requeue them.
