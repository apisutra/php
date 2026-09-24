<!-- languages --> <a href="execution.md">English</a> · <a href="../../ru/glossary/execution.md">Русский</a> <!-- /languages -->
# Execution settings <a id="section-1"></a>

| Term | Meaning | Details |
| --- | --- | --- |
| <a id="cache-attribute"></a> Cache (attribute) | A request-level cache configuration attribute. | [Contract](../reference/execution/cache.md) |
| <a id="withoutcache"></a> withoutCache() | An AbstractRequest method. | [Contract](../reference/execution/cache.md) |
| <a id="withcacheint-ttl"></a> withCache(?int $ttl) | An AbstractRequest method. | [Contract](../reference/execution/cache.md) |
| <a id="withcachewriteonlyint-ttl"></a> withCacheWriteOnly(?int $ttl) | An AbstractRequest method. | [Contract](../reference/execution/cache.md) |
| <a id="withcachereadonlyint-ttl"></a> withCacheReadOnly(?int $ttl) | An AbstractRequest method. | [Contract](../reference/execution/cache.md) |
| <a id="clearcache"></a> clearCache() | An AbstractRequest and AbstractClient method. | [Contract](../reference/execution/cache.md) |
| <a id="timeout-attribute"></a> Timeout (attribute) | A request-level timeout configuration attribute. | [Contract](../reference/execution/deadlines.md) |
| <a id="withtimeoutint-seconds"></a> withTimeout(int $seconds) | An AbstractRequest method. | [Contract](../reference/execution/deadlines.md) |
| <a id="withdelayint-ms"></a> withDelay(int $ms) | An AbstractRequest method. | [Contract](../reference/execution/deadlines.md) |
| <a id="withoutdelay"></a> withoutDelay() | An AbstractRequest method. | [Contract](../reference/execution/deadlines.md) |
| <a id="retry-attribute"></a> Retry (attribute) | A request-level retry configuration attribute. | [Contract](../reference/execution/retry.md) |
| <a id="retryon"></a> retryOn | The list of HTTP statuses eligible for retry. | [Contract](../reference/execution/retry.md) |
| <a id="retrydelaypolicyinterface"></a> RetryDelayPolicyInterface | Computes retry backoff without I/O or sending. | [Contract](../reference/execution/retry.md) |
| <a id="withretryint-attempts"></a> withRetry(int $attempts) | An AbstractRequest method. | [Contract](../reference/execution/retry.md) |
| <a id="withoutretry"></a> withoutRetry() | An AbstractRequest method. | [Contract](../reference/execution/retry.md) |
| <a id="ratelimit-attribute"></a> RateLimit (attribute) | A request-level rate-limit configuration attribute. | [Contract](../reference/execution/rate-limit.md) |
| <a id="ratelimitbehavior"></a> RateLimitBehavior | The action when the quota is exhausted: wait for permission or fail the request. | [Contract](../reference/execution/rate-limit.md) |
| <a id="withratelimitint-limit-int-period"></a> withRateLimit(int $limit, int $period) | An AbstractRequest method. | [Contract](../reference/execution/rate-limit.md) |
| <a id="withoutratelimit"></a> withoutRateLimit() | An AbstractRequest method. | [Contract](../reference/execution/rate-limit.md) |
| <a id="ratelimiter"></a> RateLimiter | An internal component that counts requests. | [Contract](../reference/execution/rate-limit.md) |
| <a id="executionmode"></a> ExecutionMode | The execution order for a set of requests: sequential or parallel. | [Contract](../reference/execution/batch-pool.md) |
| <a id="failstrategy"></a> FailStrategy | The batch response to an item error: stop the whole set, return a partial result, or ignore errors. | [Contract](../reference/execution/batch-pool.md) |
| <a id="execution-attribute"></a> Execution (attribute) | An attribute that configures nested request execution. | [Contract](../reference/execution/batch-pool.md) |
| <a id="idempotent-attribute"></a> Idempotent (attribute) | An attribute for mutating requests. | [Contract](../reference/execution/retry.md) |
| <a id="withidempotencykey"></a> withIdempotencyKey() | An AbstractRequest method. | [Contract](../reference/execution/retry.md) |
| <a id="idempotencyheader"></a> idempotencyHeader | A ClientConfig parameter. | [Contract](../reference/execution/retry.md) |

[All terms](README.md).
