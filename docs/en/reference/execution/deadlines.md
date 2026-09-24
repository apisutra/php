<!-- languages --> <a href="deadlines.md">English</a> · <a href="../../../ru/reference/execution/deadlines.md">Русский</a> <!-- /languages -->
# Timeouts, delays, and shared deadlines <a id="section-1"></a>

The built-in transport applies `timeout = 30` and `connectTimeout = 10` seconds
without additional configuration. There is no total execution limit by default.

## Timeout for one HTTP attempt <a id="section-2"></a>

```php
use ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    timeout: 30,
    connectTimeout: 10,
);
```

`timeout` limits the connection and reception of the entire response.
`connectTimeout` limits connection establishment, including the TLS handshake.
These intervals are not added together: connection time is part of the total attempt timeout.

Each field is resolved independently: runtime `withTimeout()` → `#[Timeout]` →
`ClientConfig`. An omitted value or `null` means inheritance. For example,
`withTimeout(5)` preserves the attribute/client connect timeout; at send time it is
capped by the timeout for the entire attempt. A subsequent `withTimeout(20)` resets
the previous runtime connect override to inheritance.

`0` disables the corresponding SDK limit; negative values and overflow when
converting seconds to milliseconds are configuration errors. The same rules apply
to configuration, attributes, and runtime options. Runtime methods return a separate
execution: use their return value.

```php
$result = $request->withTimeout(5, 2)->send();
$inheritedConnect = $request->withTimeout(5)->send();
```

## Optional total budget <a id="section-3"></a>

```php
use ApiSutra\Config\RetryConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    retry: new RetryConfig(attempts: 1, totalTimeoutMs: 5000),
);
```

`RetryConfig::totalTimeoutMs` is measured in milliseconds. `null` means no total
limit; a positive value creates an absolute monotonic deadline on entry to the
canonical execution, before pagination and authentication. Wall-clock changes do not affect it.
`attempts: 1` sets a budget without general retries. `withoutRetry()` and
`#[Retry(enabled: false)]` disable retries while preserving the client budget.

Authentication, HTTP, delay, rate-limit waits, backoff, Retry-After, refresh, and
refresh-lock waits consume one budget. Child auth/composite/depends-on requests
inherit the parent deadline; a smaller local limit may shorten it. Independent
calls without an external deadline receive new budgets. Batch as a whole and
await/polling do not receive a separate total deadline: limits apply to component
executions and the supplied context.

Collecting pagination all/pages/range shares one budget across the whole traversal:
three sequential 400 ms pages cannot complete with totalTimeoutMs=1000. The lazy
iterator keeps a fresh client budget per page; use withDeadline for its whole traversal.
See [pagination deadlines](pagination.md#section-23).

Before HTTP, the timeout is capped by the remaining budget, even with `timeout: 0`.
For example, 250 ms reaches the built-in adapter as 0.25 seconds, without rounding
to an unlimited zero. If a mandatory wait would leave no time to continue, the SDK
ends execution immediately; Retry-After is not shortened to send early.

Budget exhaustion produces a final `timeout` with `reason: execution_deadline_exceeded`
and `stage` in the error context, plus `ExecutionDeadlineException`. The last available
HTTP response is retained for diagnostics. An individual attempt timeout may allow
a safe retry under client policy; budget exhaustion does not. A late HTTP 200 does
not become success or trigger a successful cache write.

## Shared deadline across calls <a id="section-4"></a>

For an application sequence such as “create → upload → confirm”, pass one deadline
to each execution. For requests with a bound/resolvable client:

```php
use ApiSutra\Timing\ExecutionDeadline;

$deadline = ExecutionDeadline::afterMs(1000);

$created = $create->withDeadline($deadline)->dataOrFail();
// The application prepares the next request from the previous result.
$uploaded = $upload->withDeadline($deadline)->dataOrFail();
$confirmed = $confirm->withDeadline($deadline)->dataOrFail();
```

The object is immutable; timing starts at `afterMs()`, including time between sends.
Passing it again does not restart the clock. With three calls taking 400 ms each,
the third HTTP call gets only 200 ms. The same remaining time limits delay,
rate-limit waits, backoff, Retry-After, and auth refresh through the existing budget.

The effective deadline is the minimum of the external deadline, parent deadline,
and client `totalTimeoutMs`. This option works without RetryConfig and with
`withoutRetry()`. `withoutDeadline()` removes only this execution's external deadline;
other limits remain. The original request and ClientConfig are unchanged; the option
is not part of cache identity. Without it, behavior is unchanged; no additional
dependencies or client settings are needed.

An expired deadline, including `afterMs(0)`, produces `timeout` with
`reason: execution_deadline_exceeded`, `stage: started` before HTTP/auth/cache I/O
and execution hooks. Negative values and overflow are rejected. A wait equal to or
longer than the remaining budget is not performed. Client construction does not
become part of the cancellable operation.

Monotonic clocks are used. Built-in SystemClock instances are compatible across
clients in the current process; in tests, pass the same ClockInterface to the client
and `ExecutionDeadline::afterMs(1000, clock: $clock)`. Different custom clock objects
are rejected with `configuration_error`. A deadline is not intended for persistence
or transfer to another worker. The application owns the business loop and decision
to make the next request; the SDK honors the deadline within its executions.

## Guarantee boundaries and transport <a id="section-5"></a>

The built-in integration uses Guzzle with cURL; Laravel selects the adapter
automatically, and `HttpTransport::createDefault()` is available outside Laravel.
This setup requires `guzzlehttp/guzzle` and `ext-curl`; the core still permits other
transports without Laravel/Guzzle Client. See the [transport guide](transport.md)
for details.

An arbitrary PSR-18 client has no per-request timeout options. If a transport cannot
apply a nonzero limit, including defaults, it returns `configuration_error` before
HTTP. With `timeout: 0`, `connectTimeout: 0`, and no deadline, the ordinary PSR-18
path is allowed; the HTTP client's own limits remain in effect.

A supported transport interrupts HTTP. Arbitrary hooks, validators, cache stores,
and user PHP code are checked at boundaries after control returns: portable
synchronous PHP cannot forcibly stop them midway. Side effects already started by
that code are not rolled back. Inject clocks and sleepers only when needed, such
as in tests; user configuration is not mandatory.

## Delay <a id="section-6"></a>

```php
$config = new ClientConfig(
    baseUrl: 'https://api.example',
    delay: 200, // ms before every HTTP attempt
);
```

The default is `delay = 0`. Runtime `withDelay()` / `withoutDelay()` overrides it.
Waiting consumes the total budget when one is set.

### SleeperInterface <a id="section-8"></a>
Delay, retry and cooldown use SleeperInterface from the client constructor. The default
CooperativeSleeper blocks synchronously and yields within SDK async tasks. Replacing it
alone does not make transport I/O concurrent. A test sleeper must advance the same
monotonic clock used by execution and cooldown. RetryDelayPolicyInterface only computes
backoff; the pipeline combines it with Retry-After and [shared cooldown](cooldown.md)
and performs the wait before quota acquisition.

Shared cooldown backend I/O consumes the same budget (cooldown_read/cooldown_publish).
The optional phpredis adapter is bounded blocking, including in async tasks; see
[Redis timing and cost](../integrations/redis.md#cooldown).
