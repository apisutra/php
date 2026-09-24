<!-- languages --> <a href="caching-retry.md">English</a> · <a href="../../ru/development/caching-retry.md">Русский</a> <!-- /languages -->
# Caching, retry, and quotas in the core <a id="section-1"></a>

A brief overview of caching and retries.

## Caching <a id="section-2"></a>

- Enable caching through `CacheConfig` in `ClientConfig`.
- Supported modes: Enabled / Disabled / ReadOnly / WriteOnly.
- Keys are built from the prepared request and cache scope; traceId does not separate
  entries. See the [key contract](../reference/execution/cache.md).
- The HTTP response is stored, rather than the DTO. On a cache hit, the client applies
  its own hydrator and current rule set; the rule set is not part of the HTTP cache key.

A separate [metadata cache](attributes.md#section-4) accelerates hydration and
serialization while preserving isolation of object defaults and attribute arguments.

## Retry <a id="section-3"></a>

- Controlled by `RetryConfig` and the `shouldRetry()` policy.
- Supports backoff strategies and jitter.
- Can account for `Retry-After`.

## Rate limiting <a id="section-4"></a>

- Limits are configured through `RateLimitConfig`.
- `RateLimitBehavior` determines behavior when a limit is exceeded.

## Joint quota accounting <a id="section-5"></a>

The shared client quota and the operation's own quota are resolved as one set before
each HTTP attempt. RateLimiter coordinates Wait/Throw and the shared budget; the
backend only accepts or rejects the set atomically. By default, state is local and
windows use a monotonic clock. PSR-16 support remains only for a single quota.
See the [contract](../reference/execution/rate-limit.md) and [Redis](../reference/integrations/redis.md).

`CacheManager`, `AuthBindingResolver`, `AuthHandler`, and auth setup in AbstractClient
read the shared backend only from `ClientConfig.cacheConfig?->store`. The Cache
attribute copies the block through with(), preserving store/identity/locks;
CacheExecutionState stores the selected store for one execution. Metadata, rate
limiting, and individual extensions retain their own cache settings.
