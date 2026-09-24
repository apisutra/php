<!-- languages --> <a href="retry.md">English</a> · <a href="../../../ru/reference/execution/retry.md">Русский</a> <!-- /languages -->
# Retries <a id="section-1"></a>

Retry configuration in `RetryConfig` and the default policy.

## Basic configuration <a id="section-2"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;
use ApiSutra\Exceptions\Transport\ConnectionException;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    retry: new RetryConfig(
        attempts: 3,
        baseDelay: 200,
        maxDelay: 5000,
        backoff: BackoffStrategy::Exponential,
        jitter: true,
        retryOn: [408, 429, 500, 502, 503, 504],
        retryExceptions: [ConnectionException::class],
        totalTimeoutMs: 10_000,
    ),
);
```

## RetryConfig parameters <a id="section-3"></a>
- `attempts`: maximum main HTTP attempts, including the first.
- `baseDelay`: base delay in ms.
- `maxDelay`: maximum delay in ms.
- `backoff`: strategy (constant/linear/exponential).
- `jitter`: a random addition to the delay.
- `retryOn`: HTTP status codes eligible for retry.
- `retryExceptions`: exceptions eligible for retry.
- `totalTimeoutMs`: optional total execution budget in ms, null by default; includes
  HTTP, auth, and waits, and survives disabling retry. See [timeouts and delay](deadlines.md)
  for the contract and guarantee boundaries.
- `safeMethods`: optional list of `HttpMethod`, defaulting to GET/PUT/DELETE;
  a supplied list replaces the default, and an empty list prohibits retry without
  permission on the request class.

## Request overrides <a id="section-4"></a>
Use `#[Retry]` or runtime options (`withRetry()`/`withoutRetry()`).

`#[Retry(safe: true/false)]` overrides the request's conditional policy and the
client's safeMethods. Omitted safe and safe: null consult the optional
[RetrySafetyPolicyInterface](retry.md#section-8), then inherit configuration.
Runtime withRetry changes the attempt count while preserving other settings;
calling it does not confirm POST/PATCH safety. `#[Retry(enabled: false)]` disables
general retries; runtime can explicitly override this value.

Full contract: [Retry and Rate Limit](retry.md).

Without `retry` in `ClientConfig`, retries are disabled by default.

Retry repeats failed requests; Rate Limit limits call frequency.
Rate Limit applies independently of operation idempotency.

## Client-level retry <a id="section-5"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    retry: new RetryConfig(
        attempts: 3,
        baseDelay: 200,
        maxDelay: 5000,
        backoff: BackoffStrategy::Exponential,
        jitter: true,
        retryOn: [408, 429, 500, 502, 503, 504],
    ),
);
```

Additional options:
- `retryExceptions`: retry on exceptions, such as ConnectionException.
- `totalTimeoutMs`: optional total pipeline budget, including HTTP, authentication,
  and waits. `withoutRetry()` does not remove it. Exhaustion is final; see
  [timeouts and delay](deadlines.md) for the contract and guarantee boundaries.

### Retry safety without mandatory configuration <a id="section-6"></a>

General retries are disabled by default (`ClientConfig::retry = null`). When enabled
without extra safety settings, GET, PUT, and DELETE are allowed. POST/PATCH require
explicit confirmation of operation safety. A configured status, exception, or
attempt count does not provide that permission by itself.

For a particular API, change the client-level list:

```php
use ApiSutra\Enums\Http\HttpMethod;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    retry: new RetryConfig(safeMethods: [HttpMethod::GET]),
);
```

`safeMethods` is optional and defaults to GET/PUT/DELETE. A supplied list replaces
the default; an empty list prohibits retries without a permitting request attribute.
Add POST/PATCH only when the API guarantees these methods are safe to repeat.

On the request class, `#[Retry(safe: true)]` permits retry and `safe: false` forbids it.
Omitted `safe` and explicit `safe: null` are equivalent: client policy applies.
Without an attribute, configuration also determines safety. Permission for an
operation does not bypass attempt limits, disabled retry, or an unreplayable body.

## Retry for a specific request <a id="section-7"></a>
```php
use ApiSutra\Attributes\Behavior\Retry;
use ApiSutra\Core\AbstractRequest;

#[Retry(attempts: 5, backoff: BackoffStrategy::Linear)]
final class GetUsers extends AbstractRequest {}
```

Runtime overrides: `withRetry()` and `withoutRetry()`.
Enable retry when the API contract confirms it is safe: the operation is idempotent
or the provider supports an idempotency key for it. The SDK does not automatically
verify external API guarantees. `#[Idempotent]` and the presence of Idempotency-Key
do not authorize retry; declare safety through `safe`, a conditional request policy,
or client `safeMethods`.

### Conditional request safety <a id="section-8"></a>

The optional RetrySafetyPolicyInterface can permit POST only after a particular
response, while preserving configured network retries for GET on the same client:

```php
use ApiSutra\Attributes\Behavior\Retry;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Contracts\Interfaces\Concurrency\RetrySafetyPolicyInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\VO\Http\ProviderResponse;
use Throwable;

#[Post('/messages')]
#[Retry(attempts: 3)]
final class SendMessageRequest extends AbstractRequest implements RetrySafetyPolicyInterface
{
    public function isRetrySafe(HttpMethod $method, ?ProviderResponse $response, ?Throwable $exception): ?bool
    {
        return $exception === null && $response?->status === 429;
    }
}
```

Use this policy only if the provider contract guarantees safe retry after 429.
For other cases, the method can check a response business code. Network failures
provide exception with response=null; the previous response is not substituted.
Both values may be present after an exception in a hook that received a response.

Priority: explicit `#[Retry(safe: true/false)]` → `isRetrySafe()` result →
`RetryConfig::safeMethods`. Returning `null` means fallback. The example therefore
omits `safe: true`, which would override the conditional check. Without the interface,
safety is determined by the explicit attribute and `safeMethods`. The policy needs
no changes to ClientConfig or the required RequestInterface. A provider SDK's base request class can implement a shared policy.

The method computes safety without I/O and may be called repeatedly while preparing
auth retry. It does not initiate retry: a retry reason is required first, followed
by safety, attempts, body replay, and budget checks. `true` does not bypass the other
limits. The check also applies to auth retry. A policy exception ends the call with
`execution_error`, reason `retry_safety_check_failed`; the original exception is
retained in previous, and its message is not inserted into the public error message.

`Retry-After` is parsed identically for built-in waits and HTTP
`RateLimitException::retryAfter`: seconds and HTTP dates are supported. The exception
value is in seconds; a missing/invalid/negative/oversized value yields null, while
valid zero or a past date yields 0. Built-in waiting uses ms for 429/503
statuses and observes the total budget.

`attempts` includes the first main HTTP attempt. `#[Retry(enabled: false)]` and
`withoutRetry()` disable general retries; runtime takes precedence over the attribute.
`withRetry(attempts)` preserves other client/attribute settings, including
`safeMethods`, `retryExceptions`, and `totalTimeoutMs`. Runtime methods return a
separate execution: use the returned value; the original request is unchanged.

### RetryableException and Retry-After <a id="section-9"></a>
- `RetryableException` requests retry but does not bypass disabled retry, safety,
  body replayability, or attempt limits. `maxAttempts` can further reduce the limit;
  `retryAfter` is in seconds. If retry is impossible, the exception is preserved.
- The built-in handler does not back off before the first attempt. The first retry
  uses baseDelay; later retries use the selected strategy and jitter, capped by maxDelay.
- For 429 and 503, `Retry-After` accepts nonnegative integer seconds or an HTTP date.
  A past date gives 0; negative, fractional, invalid, or numerically unsafe values
  are ignored. Built-in waiting is `max(backoff, Retry-After)`. maxDelay caps backoff,
  not the server-requested wait.
- No retry wait occurs after attempts are exhausted or retry is refused.

Before another send, the SDK automatically restores the body. If impossible, it
preserves the original response/exception and records `retryRefusalReason` in error
context and a warning log: `operation_not_safe`, `body_not_replayable`,
`body_rewind_failed`, or `body_changed`. The body selected in `BeforeSend` becomes
the retry baseline. Later replacement with `withBody()`/`withStream()` or clearing
with `withoutBody()` produces `body_changed`: the original response/failure is retained
and the second body is not sent. The [body replacement](transport.md#section-3)
contract does not change operation safety policy.
Details: [file retries](../files/uploads.md#section-3).

## Idempotency <a id="section-10"></a>
```php
use ApiSutra\Attributes\Behavior\Idempotent;

#[Idempotent]
final class CreateOrder extends AbstractRequest {}
```

Details:
- The default header comes from `ClientConfig::idempotencyHeader`.
- Override it with `#[Idempotent(header: 'X-Idempotency')]`.
- Runtime key: `withIdempotencyKey('...')`.
- If no key is supplied, the SDK generates one once per execution; all attempts
  use that key, and independent executions receive different keys.
- A user key, including one already supplied as a header, is preserved.

Change the header if the provider expects a different name or already uses its own standard.

### Refresh interaction <a id="section-11"></a>
With `authRetryOn401 = true`, the SDK attempts reauthentication and resending after
401 within a separate authRetryAttempts limit. `withoutRetry()` alone does not
disable this mechanism. Operation and body safety checks apply here too: POST/PATCH
require explicit permission. Auth retry adds no ordinary backoff.
Retry is permitted only after successful refresh with a typed DTO and
`processTokenResponse()`, or after finding an updated TokenAuthenticator in the cache.
Without auth, or with `getRefreshRequest() === null`, the original 401 is preserved.
Explicit ordinary `retryOn: [401]` policy still works within normal attempts and backoff.
Refresh failure does not retry the main operation, even with `retryExceptions: [Throwable::class]`.
[Errors](../auth/tokens.md#section-11).

## File operations <a id="section-12"></a>

Binary/multipart uploads preserve their initial position for an allowed retry.
Non-seekable uploads permit one send; after removal of the string copy, this also
applies to binary. Every download attempt receives a separate temporary file;
a user sink is filled only with the final result. Local read/write errors do not
trigger HTTP retry, even with broad `retryExceptions`.
See [files](../../guides/recipes/files.md) for the detailed contract.

## Auth refresh wait failure <a id="section-13"></a>

`auth_refresh_lock_timeout` and `auth_lock_backend_error` do not trigger general HTTP
retry, even if `retryExceptions` includes their base classes. The main request is
not resent after such failure. For 401, the received HTTP context is retained;
see [refresh locks](../auth/tokens.md#section-7).

## Retry delay policy and transport decorators <a id="delay-policy"></a>

RetryDelayCalculator implements RetryDelayPolicyInterface::delayMs(RetryConfig, int): int.
The retry number starts at 1. The policy returns nonnegative, representable milliseconds;
it must not perform I/O, sleep or send HTTP. A thrown exception or invalid delay becomes
configuration_error. Advanced users can inject a policy into Pipeline/RetrySender.
There is no second sending handler or separate async retry implementation.

```php
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Retry\RetryDelayPolicyInterface;

final class FixedRetryDelay implements RetryDelayPolicyInterface
{
    public function delayMs(RetryConfig $config, int $retryNumber): int
    {
        return min(250, $config->maxDelay);
    }
}
```

A transport decorator is the per-attempt extension for metrics or synthetic responses.
Implement both send and sendAsync, preserving timeout, destination, file and concurrency
capabilities. It is invoked after scope/body/replay, cooldown and quota admission; a local
refusal never enters the decorator. BeforeSend runs once per execution, not per attempt.
Prepare credentials/origin/method/body before admission: replacing them inside a decorator
invalidates previous checks. The SDK cannot sandbox arbitrary transport code.

Use the delay policy for backoff computation, a transport decorator for per-attempt
sending behavior, and BeforeSend for one-time preparation. The pipeline waits before
quota acquisition and keeps [shared cooldown](cooldown.md) separate from retry safety.
