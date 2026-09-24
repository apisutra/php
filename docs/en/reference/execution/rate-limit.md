<!-- languages --> <a href="rate-limit.md">English</a> · <a href="../../../ru/reference/execution/rate-limit.md">Русский</a> <!-- /languages -->
# Request quotas <a id="section-1"></a>

Limits are disabled by default (`ClientConfig::rateLimit = null`). One setting is
enough for a shared local quota; Redis, Laravel, and prefixes are unnecessary:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    rateLimit: new RateLimitConfig(limit: 100, period: 60),
);
```

`rateLimit` caps the total permits for participating client operations.
An operation attribute adds an independent limit:

```php
use ApiSutra\Attributes\Behavior\RateLimit;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[Get('/reports')]
#[RateLimit(limit: 5, period: 60, behavior: RateLimitBehavior::Throw)]
final class ReportsRequest extends AbstractRequest {}
```

With a shared quota of 100/min, five reports consume five units from each counter.
A sixth report consumes neither quota. If the shared quota has only two permits
left, only two reports can run, even if their own quota still has capacity.

## Parameters <a id="section-2"></a>

| RateLimitConfig | Default | Meaning |
| --- | --- | --- |
| limit | 100 | Positive number of permits |
| period | 60 | Positive window duration in **seconds** |
| behavior | RateLimitBehavior::Wait | Wait or reject locally with Throw |
| store | null | PSR-16 path for one quota only, without an explicit backend |
| key | null | Explicit quota group; automatic keys are described below |

| ClientConfig | Default | Meaning |
| --- | --- | --- |
| rateLimit | null | Shared quota |
| includeClientQuota | true | Operation participation unless refined by the attribute |
| rateLimitBackend | null | Atomic backend; otherwise local counters or the compatible single-quota PSR-16 path |

The period must fit the built-in sleeper's microsecond representation
(`period <= intdiv(PHP_INT_MAX, 1_000_000)`); window-end overflow is also rejected.
Nonpositive values cause ConfigurationException. A zero limit does not disable the mechanism.

## Participation and overrides <a id="section-3"></a>

For AbstractRequest, the operation quota is selected in this order: runtime options →
instance setting → attribute with a complete pair → no operation quota.
The shared quota comes separately from ClientConfig.rateLimit and is not replaced by this selection.

- `#[RateLimit(limit: 5, period: 60)]` adds an operation quota.
- `includeClientQuota: null` or omission inherits the client flag.
- `#[RateLimit(limit: 5, period: 60, includeClientQuota: false)]` counts only the operation quota.
- `#[RateLimit(includeClientQuota: false)]` excludes the shared quota; without an
  operation runtime override, this request has no quotas. Shared quota numbers are
  not copied into an operation quota.
- `#[RateLimit(includeClientQuota: true)]` opts in when the client default is false.
  If no shared quota exists, the flag does not create one.
- `withoutRateLimit()` disables all quotas for this execution; `withRateLimit()`
  re-enables limiting and sets an operation quota according to override priority.

The limit/period pair must be complete or absent. An empty attribute changes nothing;
a key or non-Wait behavior without the pair is rejected. Attribute values are validated
when applied during execution, before backend/HTTP access; metadata/inventory reads
do not apply quotas. RequestInterface implementations outside AbstractRequest retain
the previous path without attribute introspection or runtime overrides: only the
client participation/quota rule applies.

## Backends and keys <a id="section-4"></a>

Without an external backend, state belongs to the client instance and survives
fake/record/playback switches. A new client creates independent local counters.
Local windows use monotonicMs and are unaffected by wall-clock changes.

On the atomic path, the shared quota has a separate client group; the operation
quota has an operation group and defaults to the request class. `key` explicitly
groups operations. Identical key text on shared and operation quotas does not merge
them. Identifiers are hashed; query/body/credentials are not added. During an active
window, a group must use the same limit/period: conflict causes configuration_error
without resetting the counter. A new definition is allowed after the window ends.

For multiple workers, use the built-in [Redis/phpredis backend](../integrations/redis.md).
It acquires the entire set atomically. Redis is explicitly connected; the SDK does
not select it just because Laravel is present.

PSR-16 stores remain supported for **one applicable quota with rateLimitBackend=null**.
An operation quota uses its own store or the client's store. This path retains its
old key: a hash of `apisutra.rate-limit.v2:` + custom key/baseUrl. Direct
RateLimiter::acquire also retains its API with a supplied key and Unix seconds.
get/set does not guarantee cross-process atomicity, even when the cache store uses Redis.

Two quotas with a store, or an explicit backend together with a store, are incompatible.
Errors occur before reads/writes and HTTP; a client store/backend conflict fails
at configuration construction. The SDK neither ignores the store nor deducts half
the set in another store. HTTP/auth cache settings are unaffected.

## Waiting and errors <a id="section-5"></a>

Each actual HTTP attempt obtains one permit from each quota. Retry, auth recovery,
child requests, and pages also count; a cache hit or an HTTP-free composite wrapper
does not consume quota. The algorithm is a fixed window starting with its first
permit; it is neither sliding window nor token bucket and does not guarantee no
burst at the boundary between windows.

RateLimiter manages waits for every backend. With several blocking Wait quotas,
it waits for the longest duration and rechecks the entire set. A nonblocking Throw
quota does not prevent waiting. If any **blocking** quota uses Throw, it immediately
raises RateLimitException; retryAfter is the maximum of all blocking windows,
rounded up to seconds. This is an estimate, not a reservation of a future slot.

Waiting and I/O count toward `RetryConfig.totalTimeoutMs` when set, independently
of HTTP timeout. HTTP does not start after budget expiry. Available local
transport/timeouts/body/destination checks run before a permit is deducted.

| Situation | Result |
| --- | --- |
| Exhaustion with Throw | rate_limited, reason=local_rate_limit_exceeded, retryAfter in seconds |
| Backend error | execution_error, reason=rate_limit_backend_error, stage=rate_limit_store; no automatic retry |
| Deadline | timeout with the corresponding stage |
| Invalid configuration/definition conflict | configuration_error before HTTP |

Standard result-first, throwOnErrors, and Promise contracts apply. A local rejection
does not create HTTP 429: RateLimitException.response=null. If a previous attempt
received a response, the exception retains lastResponse, and the final result may
contain that response. Raw previous is available explicitly; standard diagnostics
do not reveal backend error text.

A permit does not mean HTTP was delivered. After a grant, a deadline may expire,
a connection may fail, or a worker may stop. There is no automatic refund: the SDK
does not know whether the request reached the external API. Backend state loss may reset quotas.

## Custom atomic backend <a id="section-6"></a>

Implement RateLimitBackendInterface from ApiSutra\RateLimiting:
`tryAcquire(array $quotas, ?int $timeoutMs = null): RateLimitDecision`.
RateLimitQuota contains key, limit, and **periodMs**. The backend returns granted
or blockedIds and a positive **retryAfterMs**, checking all quotas before writing.
Duplicate IDs are normalized into one deduction; conflicting definitions are rejected.
The backend neither sleeps nor performs HTTP. timeoutMs is the remaining duration
for the call, not an absolute time. RateLimiter provides shared Wait/Throw handling
and rechecking after waiting.

## Choosing quotas <a id="section-7"></a>

A client rateLimit defines the shared quota. An operation limit supplements it:
a 5/min report consumes both its operation quota and a shared 100/min quota.
Use includeClientQuota=false for an explicit exception, or omit the client quota
when only operation limits are needed. Attribute limit/period may be null.

Use the local backend for one instance or Redis with a common scope for workers.
PSR-16 stores support only a single quota without an explicit backend.
Unsupported-timeout checks precede acquire, including on the PSR-16 path:
invalid transport configuration fails before quota is consumed.

## Client-level rate limit <a id="section-8"></a>
```php
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

$config = $config->with(rateLimit: new RateLimitConfig(
    limit: 60,
    period: 60,
    behavior: RateLimitBehavior::Wait,
));
```

## Rate limit for a specific request <a id="section-9"></a>
```php
use ApiSutra\Attributes\Behavior\RateLimit;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[RateLimit(limit: 10, period: 1, behavior: RateLimitBehavior::Throw)]
final class Search extends AbstractRequest {}
```

`ClientConfig::rateLimit` sets the shared quota; the attribute and `withRateLimit()`
set an additional operation quota. Each HTTP attempt needs permission from both.
`includeClientQuota: false` excludes the shared quota; `withoutRateLimit()` disables all.
Without backend configuration, accounting is local to the client instance. The shared
quota uses one client group; operation quotas default to request class, with custom
keys grouping operations. Shared and operation quota namespaces are distinct.

PSR-16 stores support only one applicable quota and do not guarantee cross-process
atomicity. An optional [Redis backend](../integrations/redis.md) supports multiple
workers. Defaults, waiting, keys, and compatibility changes:
[full rate-limit contract](rate-limit.md).


Server Retry-After is handled by independent [cooldown](cooldown.md). withoutRateLimit
does not disable it; a Redis quota backend does not share cooldown across clients/processes.

## Refusal instead of waiting in an execution scope <a id="admission-scope"></a>

An integration may enclose calls in AdmissionScope: a real local admission refusal escapes
as AdmissionRefused before ordinary FAILED delivery and the exception factory. Client
configuration, per-attempt HTTP quotas, auth and retry remain; other scopes are independent.
This is control flow, not a diagnostic event or a server HTTP 429.
[Laravel middleware](https://github.com/apisutra/laravel/blob/master/docs/en/reference/integrations/queue.md)
uses it for repeatable jobs. Without this scope send/sendAsync retain result-first behavior.
The core requires neither a worker nor a queue.
