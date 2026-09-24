<!-- languages --> <a href="batch-pool.md">English</a> · <a href="../../../ru/reference/execution/batch-pool.md">Русский</a> <!-- /languages -->
# Batch and pool <a id="section-1"></a>

A pool controls concurrency for a set of independent requests. Actual HTTP parallelism
depends on the transport.

For large or unknown sources, use [consume/consumeAsync](pool-consumption.md) to process results without collecting them. `send()` and `sendAsync()` still prepare the whole source first and return a full collection.

## When to use a pool <a id="section-2"></a>
- Bulk requests over a list of identifiers.
- Parallel loading of reference data and related resources.
- Background updates where execution order does not matter.

## When it does not fit <a id="section-3"></a>
- Requests depend on each other or require strict ordering.
- The provider requires sequential access; prefer `batch()->sequential()`.

## Settings <a id="section-4"></a>
- `concurrency` — withConcurrency → explicit argument → PoolConfig::concurrency → 5; omitted/null argument uses the default
- `stopOnFailure`: stop starting new requests after the first FAILED result; `withStopOnFailure()` override → `PoolConfig::stopOnFailure` → false.

`withConcurrency(int|callable|ConcurrencyResolverInterface $concurrency)` returns a
copy; the last override takes priority over the creation argument and configuration.
The original pool is unchanged. The resolver runs once with `(total, 0)` and must
return int: strings, floats, bool and null produce ConfigurationException before HTTP.
Integers below 1 normalize to 1. This applies to send/consume and their async pairs.
For consume a resolver requires a known source size; see its [contract](pool-consumption.md).
Copying a builder does not clone its input iterable. If it holds a Generator,
create a fresh source for each run, including runs through different builder copies.

Defaults: `concurrency = 5`, `stopOnFailure = false`.
Change `concurrency` when the provider limits parallelism or bulk requests need acceleration.

`withStopOnFailure(bool $enabled = true)` returns a copy of the pool builder without
changing the original builder or client configuration. Pass `false` to disable a
client-level setting for this pool. The override applies to `send()`, `sendAsync()`,
`consume()`, and `consumeAsync()`. Already started requests finish and reach their handlers.

For an existing `$client` and iterable `$requests`, enable it for one import:

```php
$summary = $client->pool($requests)->withStopOnFailure()->consume();
```

Details:
- Pool accepts **only** `RequestInterface`.
- `concurrency` is fixed when the pool starts.

## Basic configuration <a id="section-5"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PoolConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    pool: new PoolConfig(stopOnFailure: true),
);
```

## Usage <a id="section-6"></a>
```php
$result = $client->pool($requests, 5)->send();
```

## Pool promises <a id="section-7"></a>
`sendAsync()` returns `ResultPromiseInterface<PoolResult>`.

## Additional parameters <a id="section-8"></a>
```php
use ApiSutra\Contracts\Interfaces\Concurrency\ConcurrencyResolverInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Result\ExecutionResult;
use Throwable;

final class ExampleConcurrencyResolver implements ConcurrencyResolverInterface
{
    public function getConcurrency(int $pending, int $completed): int
    {
        return $pending > 50 ? 10 : 5;
    }
}

$pool = $client->pool(
    $requests,
    new ExampleConcurrencyResolver(),
);

$result = $pool
    ->withResponseHandler(function (ExecutionResult $result, RequestInterface $request): void {})
    ->withExceptionHandler(function (Throwable $exception, RequestInterface $request): void {})
    ->send();
```

Use `ConcurrencyResolverInterface` to choose the limit at pool start; it is not
recalculated as requests complete.

Batch executes a set of requests assembled **at runtime**. Unlike Composite, the
request list is assembled in place, and the result is a `BatchResult` containing
nested `ExecutionResult` objects.

## When to use batch <a id="section-9"></a>
- Bulk requests over a list of identifiers.
- Combining data from different endpoints without a fixed schema.
- Simple concurrent processing (sequential/parallel).

## Basic usage <a id="section-10"></a>
```php
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;

$result = $client
    ->batch($requests)
    ->withMode(ExecutionMode::Parallel)
    ->withFailStrategy(FailStrategy::Partial)
    ->withConcurrency(10)
    ->send();
```

## Configuration through BatchConfig <a id="section-11"></a>
Batch `withConcurrency(int)`, `withFailStrategy(FailStrategy)`, `withMode()`,
`parallel()` and `sequential()` return copies too. Keep the returned builder;
calling a modifier and discarding its return value leaves the original unchanged.
Only pool accepts a concurrency callable/resolver; batch accepts an integer.

```php
use ApiSutra\Config\BatchConfig;

$config = new BatchConfig(
    mode: ExecutionMode::Parallel,
    failStrategy: FailStrategy::Partial,
    concurrency: 10,
);

$result = $client->batch($requests, $config)->send();
```

Defaults: `Sequential`, `FailAll`, `concurrency = 5`.
Without customization, plain `batch($requests)` is sufficient.

## Allowed batch items <a id="section-12"></a>
Batch accepts **RequestInterface** or these special forms:

1) **An existing request instance**
```php
$requests = [
    new GetUser($id),
    new GetOrders($id),
];
```

2) **Callable** (receives the parent request or null)
```php
$requests = [
    fn ($parent) => new GetUser($parent?->userId ?? '0'),
];
```

3) **class-string** (constructor values come from the parent request)
```php
$requests = [
    GetUser::class,
];
```

Without a parent request, constructor parameters receive defaults or `null`.

## Execution modes <a id="section-13"></a>
- `ExecutionMode::Sequential`: strict order.
- `ExecutionMode::Parallel`: parallel execution with a concurrency limit.

## FailStrategy <a id="section-14"></a>
- `FailAll`: stop issuing new requests after the first FAILED child; already issued work finishes.
- `Partial` / `IgnoreErrors`: continue after a FAILED child. In batch, both retain
  failed child results and errors; neither forces the aggregate to be successful.

These strategies control continuation. The final status is calculated from the
collected results: all successful → SUCCESS, all failed → FAILED, a mixture or a
partial child → PARTIAL. An empty batch currently also has PARTIAL status.
`throwOnErrors` applies to this final status, not to each internal child:

| Example with throwOnErrors true | Behavior |
| --- | --- |
| Partial, first request fails and two succeed | All three run; return PARTIAL with data/errors in child results |
| Partial, all three requests fail | All three run; deliver an exception for the final FAILED aggregate |
| FailAll, first request fails, sequential mode | Remaining requests do not run; deliver an exception for FAILED |

A PARTIAL aggregate returns normally even with throwOnErrors true. Inspect its
`failed()`/`errors` when partial success needs additional application handling.

## Batch result <a id="section-15"></a>
`send()` returns `BatchResult`, where:
- `results()`: the collection of all results.
- `successful()` / `failed()`: filtered collections.
- `meta()`: `BatchMeta` (total/success/failed/partial).

## Batch promises <a id="section-16"></a>
`sendAsync()` returns `ResultPromiseInterface<BatchResult>`.

## Pool vs Batch <a id="section-17"></a>
- **Batch**: execution strategy plus failure policy (sequential/parallel).
- **Pool**: concurrent independent requests with result and exception handlers.
Use batch when ordering or a failure strategy matters.
Pool is convenient for processing results as they become available.

The built-in Guzzle adapter overlaps HTTP up to the concurrency limit. Callbacks run
in completion order; the result collection retains input order. Unsupported async
transports produce configuration_error. Synchronous sequential batch and synchronous
pool with concurrency 1 still accept synchronous transports. Async entry points
require concurrency support even with a limit of 1. Keep and await the returned promise.

## Bulk loading (Batch) <a id="section-18"></a>
```php
$result = $client
    ->batch([GetUser::class, GetOrders::class])
    ->parallel()
    ->withConcurrency(5)
    ->send();
```

## Quick fan-out (Pool) <a id="section-19"></a>
```php
$result = $client->pool($requests, 10)->send();
```

## Child result diagnostics <a id="section-20"></a>

In throw/rejection mode, items retain the trace, audit, debug, response, and original
error of their specific send. Callbacks retain normal error delivery.
A standalone batch/pool combines independent roots, so the aggregate trace may be
`null`. Composite children inherit the parent's trace and receive separate executionIds.
[Correlation and safe logs](../results/observability.md).

## Final delivery and callbacks <a id="section-21"></a>

FailStrategy controls whether new children run; throwOnErrors controls delivery of
the final status. Partial/IgnoreErrors continue after a failed child in either mode.
FailAll and pool stopOnFailure stop new work, while already issued promises finish.
Final FAILED throws/rejects with throwOnErrors true; SUCCESS/PARTIAL return normally.

Pool callback selection depends on status: FAILED calls the exception handler when
present, otherwise the response handler. SUCCESS/PARTIAL call the response handler.
If a callback or its exception factory fails, no further requests or callbacks start;
issued work is drained before the original failure propagates. No automatic aggregate
throw follows. Canonical child results stay unchanged. [Cause selection and factory counts](../results/exceptions.md#section-4).
