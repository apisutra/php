<!-- languages --> <a href="promises.md">English</a> · <a href="../../../ru/reference/results/promises.md">Русский</a> <!-- /languages -->
# Typed promises <a id="section-1"></a>

`ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface<T>` is compatible
with Guzzle PromiseInterface. T is the PHPDoc type of the fulfilled value: ResultHandle
for a single send, PoolResult/BatchResult for groups, and PoolSummary for consumeAsync.
`wait()` returns T; `wait(false)` returns null. A rejected promise throws on ordinary
wait; wait(false) waits without delivering a value or throwing the rejection.

## Values and composition <a id="values"></a>

| Sync operation | Async operation | Value after wait |
| --- | --- | --- |
| `send()` | `sendAsync()` | `ResultHandle` |
| `sendInContext()` | `sendInContextAsync()` | `ResultHandle` with a parent execution |
| `request->resolved()` | `request->resolvedAsync()` | `ResolvedResultInterface` |
| `batch->send()` | `batch->sendAsync()` | `BatchResult` |
| `pool->send()` | `pool->sendAsync()` | `PoolResult` |
| `pool->consume()` | `pool->consumeAsync()` | `PoolSummary` |

Start independent calls before waiting. Each new send executes again; repeated
waits and reading the same completed handle reuse its result. A promise is not a
ResultHandle: call `wait()` before `dataOrFail()`, `raw()` or `resolved()`.

For a configured `$client` and `$request` / `$requests`:

```php
use ApiSutra\Result\ResultHandle;
use ApiSutra\Result\PoolSummary;

$promise = $client->sendAsync($request);
$handle = $promise->wait(); // ResultHandle.
$summaryPromise = $promise->then(
    fn (ResultHandle $handle) => $client->pool($requests)->consumeAsync(),
);
$total = $summaryPromise->then(static fn (PoolSummary $summary): int => $summary->total)->wait();
```

then/otherwise preserve ordinary return types and unwrap the value type of our
promises returned by callbacks. otherwise adds the recovery type to the original T.
An untyped external Guzzle Promise yields mixed. PHPStan may also infer mixed for
a callback returning a non-final class such as ExecutionResult: a subclass could
implement PromiseInterface. The final ResultHandle/PoolSummary types retain precision. Utils::all correctly waits for our
promises; precise types of its result elements are not additionally described.

Verified with PHPStan 2.2.13 at level 8. This is a tested version, not an established
minimum; PhpStorm completion is unverified. Consumers do not need package stub files.
T is checked by static analysis, not by PHP at runtime. Knowing ResultHandle does
not infer a specific DTO from dataOrFail(): that method retains mixed, while DTO
construction and Returns validation happen during execution.

## Failed results and rejected promises <a id="errors"></a>

SDK errors during client resolution, startup and async delivery become rejections.
FAILED with throwOnErrors=false remains a completed failed result; with true the
promise rejects with the selected exception. PHP argument type errors before method
entry do not become rejections.

`otherwise()` handles a **rejection**, not a fulfilled handle with FAILED status.
With the default `throwOnErrors: false`, choose whether to inspect that handle or
turn the failure into a rejection by calling `dataOrFail()` inside `then()`:

```php
use ApiSutra\Result\ResultHandle;

// $client is configured; $request is an SDK request; $recordFailure is an application callback.
$data = $client->sendAsync($request)
    ->then(static fn (ResultHandle $handle) => $handle->dataOrFail())
    ->otherwise(static function (Throwable $error) use ($recordFailure): null {
        $recordFailure($error); // Apply your error policy; null is an explicit fallback.
        return null;
    })
    ->wait();
```

With `throwOnErrors: true`, a FAILED operation already rejects the source promise.
`wait(false)` does not convert a failure into success: a later `wait()` still throws
that rejection. Handle PARTIAL through result status and counters; dataOrFail does
not throw for PARTIAL. `Utils::all()` rejects if an input promise rejects; do not
assume this cancels all other started calls. Keep their promises and await or cancel
them explicitly. SDK-owned batch/pool cancellation has its [own contract](../execution/transport.md#section-2).

## Ownership and cancellation <a id="ownership"></a>

The promise exposes Guzzle resolve/reject for manual fulfillment/rejection. The SDK
manages completion of its operations; applications use wait/then/otherwise/cancel.
Calling resolve/reject manually interferes with the result and does not control HTTP;
reject does not replace cancel. A completed ResultHandle has no such methods and
does not manage an unfinished send.

Provider continuation starts after delivery: `sendAsync($request)->wait()->await()`.
Once the promise is fulfilled, cancelling it does not cancel subsequent provider
polling. Its cooperative behavior depends on the context where await is called.

Run the [complete local example](../../../example/async-results/run.php) with
`php docs/example/async-results/run.php`. It combines single/pool/consume promises,
unwraps a nested promise, and contrasts FAILED delivery with rejection. It uses
MockTransport to demonstrate API semantics, not network concurrency or performance.
