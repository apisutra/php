<!-- languages --> <a href="behavior.md">English</a> · <a href="../../../ru/reference/attributes/behavior.md">Русский</a> <!-- /languages -->
# Behavior attributes <a id="section-1"></a>

## Signatures and targets <a id="section-2"></a>

Class names belong to `ApiSutra\Attributes\Behavior`.
Signatures show constructor parameters and defaults; target identifies where the
attribute is allowed. Behavior and priorities are covered in the topic links below.

| Attribute | Target | Constructor |
| --- | --- | --- |
| Cache | CLASS | `Cache(?int $ttl = null, CacheMode $mode = CacheMode::Enabled, ?string $key = null)` |
| Execution | CLASS | `Execution(ExecutionMode $mode = ExecutionMode::Sequential, FailStrategy $failStrategy = FailStrategy::FailAll)` |
| Idempotent | CLASS | `Idempotent(?string $header = null)` |
| NoAuth | CLASS | `NoAuth()` |
| Pagination | CLASS | `Pagination(?string $pageParam = null, ?string $limitParam = null, ?string $cursorParam = null, ?string $metaPath = null, ?string $itemsPath = null, ?bool $offsetBased = null, ?string $metaResolver = null, ?string $itemsType = null, ?string $itemsCollection = null, ?string $itemsCollectionFactory = null, ?int $maxPages = null)` |
| RateLimit | CLASS | `RateLimit(?int $limit = null, ?int $period = null, RateLimitBehavior $behavior = RateLimitBehavior::Wait, ?string $key = null, ?bool $includeClientQuota = null)` |
| Retry | CLASS | `Retry(bool $enabled = true, int $attempts = 3, int $baseDelay = 100, int $maxDelay = 10000, BackoffStrategy $backoff = BackoffStrategy::Exponential, bool $jitter = true, array $retryOn = [429, 500, 502, 503, 504], ?bool $safe = null)` |
| Timeout | CLASS | `Timeout(int $seconds, ?int $connectTimeout = null)` |

Request behavior attributes apply to the request class and override ClientConfig defaults.

## When to use them <a id="section-3"></a>
- **Cache** — frequently repeated requests whose results can be cached.
- **Retry** — transient network/5xx/429 errors.
- **Timeout** — local time limits for a particular request.
- **RateLimit** — protect the provider from excessive calls.
- **Execution** — control batch/composite behavior.
- **Idempotent** — send an idempotency key to APIs supporting it.
- **NoAuth** — public or service requests without authentication.
- **Pagination** — override request pagination settings.

## Cache <a id="section-4"></a>
**Parameters:**
- `ttl?: int`
- `mode: CacheMode = Enabled`
- `key?: string`

Example:
```php
use ApiSutra\Attributes\Behavior\Cache;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Cache\CacheMode;

#[Cache(ttl: 60, mode: CacheMode::Enabled)]
final class CachedRequest extends AbstractRequest {}
```

key combines request variants within the automatic identity/tenant namespace.
`CacheConfig::prefix` and `withCacheScope()` provide additional separation and are
optional. With undefined identity, the HTTP cache is not used. The attribute permits
caching an operation, including POST, unless the effective mode is Disabled.
See [Cache](../execution/cache.md) for details.

## Retry <a id="section-5"></a>
**Parameters:**
- `enabled: bool = true`
- `attempts: int = 3`
- `baseDelay: int = 100`
- `maxDelay: int = 10000`
- `backoff: BackoffStrategy = Exponential`
- `jitter: bool = true`
- `retryOn: array = [429, 500, 502, 503, 504]`
- `safe: ?bool = null` — operation safety permission; null defers to the request's conditional policy, then client configuration.

Example:
```php
use ApiSutra\Attributes\Behavior\Retry;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;

#[Retry(attempts: 5, baseDelay: 200, backoff: BackoffStrategy::Exponential)]
final class RetryRequest extends AbstractRequest {}
```

`safe` is optional: omission and explicit null are equivalent. true/false override the
request's RetrySafetyPolicyInterface and the client's safeMethods; true does not
bypass enabled=false, attempt limits, or a non-replayable body. Without the interface,
null inherits client configuration. `enabled: false` disables general retries; runtime
configuration takes priority. See [safety policy](../execution/retry.md#section-6).

## Timeout <a id="section-6"></a>
**Parameters:**
- `seconds: int`
- `connectTimeout?: int`

Example:
```php
use ApiSutra\Attributes\Behavior\Timeout;

#[Timeout(seconds: 5, connectTimeout: 2)]
final class TimeoutRequest extends AbstractRequest {}
```

Units are seconds. Omitted/null connect timeout inherits configuration; 0 disables
the corresponding SDK limit. Runtime takes priority over the attribute.
See [Timeouts & Delay](../execution/deadlines.md) for the full contract.

## RateLimit <a id="section-7"></a>

The operation's own quota applies alongside `ClientConfig::rateLimit`.
See the [full contract and defaults](../execution/rate-limit.md).

Parameters:
- `limit: ?int = null`, `period: ?int = null` — a positive pair; period is in seconds.
- `behavior: RateLimitBehavior = Wait`
- `key: ?string = null` — explicit operation group.
- `includeClientQuota: ?bool = null` — inherits the client flag (default true).

```php
use ApiSutra\Attributes\Behavior\RateLimit;
use ApiSutra\Core\AbstractRequest;

#[RateLimit(limit: 10, period: 60)]
final class RateLimitedRequest extends AbstractRequest {}

#[RateLimit(includeClientQuota: false)]
final class IndependentRequest extends AbstractRequest {}
```

Without the pair, no operation quota is created. An empty attribute is allowed;
key or Throw without the pair is a configuration error. Values are checked when
applied before sending; reading metadata alone does not trigger validation.

## Execution <a id="section-8"></a>
**Parameters:**
- `mode: ExecutionMode = Sequential`
- `failStrategy: FailStrategy = FailAll`

Example:
```php
use ApiSutra\Attributes\Behavior\Execution;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;

#[Execution(mode: ExecutionMode::Parallel, failStrategy: FailStrategy::Partial)]
final class ParallelRequest extends AbstractRequest {}
```

## Idempotent <a id="section-9"></a>
**Parameters:**
- `header?: string` — idempotency header name.

Example:
```php
use ApiSutra\Attributes\Behavior\Idempotent;

#[Idempotent(header: 'Idempotency-Key')]
final class IdempotentRequest extends AbstractRequest {}
```

## NoAuth <a id="section-10"></a>
**Parameters:** none.
**Effect:** disables authentication for the request.

Example:
```php
use ApiSutra\Attributes\Behavior\NoAuth;

#[NoAuth]
final class PublicRequest extends AbstractRequest {}
```

## Pagination <a id="section-11"></a>
**Parameters (PaginationConfig overrides):**
- `pageParam?: string`, `limitParam?: string`, `cursorParam?: string`
- `metaPath?: string`, `itemsPath?: string`
- `offsetBased?: bool`
- `metaResolver?: string`
- `itemsType?: string`
- `itemsCollection?: string`
- `itemsCollectionFactory?: string`
- `maxPages?: int`

Settings come from `ClientConfig::paginationConfig` by default.
Without that config, defaults are:
- `pageParam = page`, `limitParam = limit`, `cursorParam = null`
- `metaPath = meta`, `itemsPath = data`
- `offsetBased = false`, `maxPages = 1000`

Example:
```php
use ApiSutra\Attributes\Behavior\Pagination;

#[Pagination(itemsPath: 'data.items', metaPath: 'meta')]
final class PaginatedRequest extends AbstractRequest {}
```

Cache sets HTTP cache parameters; without `ClientConfig.cacheConfig` or its store,
it does not create or restore storage. See [cache setup](../execution/cache.md).

## SkipContinuation <a id="skip-continuation"></a>

`SkipContinuation()`

Apply `#[SkipContinuation]` to a service request that must bypass the client’s continuation mode mapping, such as a token exchange. It does not disable timeouts, quotas or tracing. See [OAuth2](../auth/oauth2.md).

## Cooldown <a id="cooldown"></a>

`Cooldown(?bool $enabled = null, ?string $group = null, ?RateLimitBehavior $behavior = null, ?int $maxAdditionalWaitMs = null)`

Class-level overrides of [server cooldown](../execution/cooldown.md). Null fields inherit client settings; a runtime CooldownConfig replaces the complete rule.
