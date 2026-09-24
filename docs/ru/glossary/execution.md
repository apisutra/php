<!-- languages --> <a href="../../en/glossary/execution.md">English</a> · <a href="execution.md">Русский</a> <!-- /languages -->
# Настройки выполнения <a id="section-1"></a>

| Термин | Значение | Подробнее |
| --- | --- | --- |
| <a id="cache-attribute"></a> Cache (атрибут) | Атрибут конфигурации кеширования на уровне запроса. | [Контракт](../reference/execution/cache.md) |
| <a id="withoutcache"></a> withoutCache() | Метод AbstractRequest. | [Контракт](../reference/execution/cache.md) |
| <a id="withcacheint-ttl"></a> withCache(?int $ttl) | Метод AbstractRequest. | [Контракт](../reference/execution/cache.md) |
| <a id="withcachewriteonlyint-ttl"></a> withCacheWriteOnly(?int $ttl) | Метод AbstractRequest. | [Контракт](../reference/execution/cache.md) |
| <a id="withcachereadonlyint-ttl"></a> withCacheReadOnly(?int $ttl) | Метод AbstractRequest. | [Контракт](../reference/execution/cache.md) |
| <a id="clearcache"></a> clearCache() | Метод AbstractRequest и AbstractClient. | [Контракт](../reference/execution/cache.md) |
| <a id="timeout-attribute"></a> Timeout (атрибут) | Атрибут конфигурации таймаута на уровне запроса. | [Контракт](../reference/execution/deadlines.md) |
| <a id="withtimeoutint-seconds"></a> withTimeout(int $seconds) | Метод AbstractRequest. | [Контракт](../reference/execution/deadlines.md) |
| <a id="withdelayint-ms"></a> withDelay(int $ms) | Метод AbstractRequest. | [Контракт](../reference/execution/deadlines.md) |
| <a id="withoutdelay"></a> withoutDelay() | Метод AbstractRequest. | [Контракт](../reference/execution/deadlines.md) |
| <a id="retry-attribute"></a> Retry (атрибут) | Атрибут конфигурации retry на уровне запроса. | [Контракт](../reference/execution/retry.md) |
| <a id="retryon"></a> retryOn | Список HTTP‑статусов для retry. | [Контракт](../reference/execution/retry.md) |
| <a id="retrydelaypolicyinterface"></a> RetryDelayPolicyInterface | Вычисляет backoff без I/O и отправки. | [Контракт](../reference/execution/retry.md) |
| <a id="withretryint-attempts"></a> withRetry(int $attempts) | Метод AbstractRequest. | [Контракт](../reference/execution/retry.md) |
| <a id="withoutretry"></a> withoutRetry() | Метод AbstractRequest. | [Контракт](../reference/execution/retry.md) |
| <a id="ratelimit-attribute"></a> RateLimit (атрибут) | Атрибут конфигурации rate‑limit на уровне запроса. | [Контракт](../reference/execution/rate-limit.md) |
| <a id="ratelimitbehavior"></a> RateLimitBehavior | Действие при исчерпании квоты: дождаться разрешения или завершить запрос ошибкой. | [Контракт](../reference/execution/rate-limit.md) |
| <a id="withratelimitint-limit-int-period"></a> withRateLimit(int $limit, int $period) | Метод AbstractRequest. | [Контракт](../reference/execution/rate-limit.md) |
| <a id="withoutratelimit"></a> withoutRateLimit() | Метод AbstractRequest. | [Контракт](../reference/execution/rate-limit.md) |
| <a id="ratelimiter"></a> RateLimiter | Внутренний компонент для подсчёта запросов. | [Контракт](../reference/execution/rate-limit.md) |
| <a id="executionmode"></a> ExecutionMode | Порядок исполнения набора запросов: последовательный или параллельный. | [Контракт](../reference/execution/batch-pool.md) |
| <a id="failstrategy"></a> FailStrategy | Реакция batch на ошибку элемента: остановить весь набор, вернуть частичный результат или игнорировать ошибки. | [Контракт](../reference/execution/batch-pool.md) |
| <a id="execution-attribute"></a> Execution (атрибут) | Атрибут конфигурации выполнения вложенных запросов. | [Контракт](../reference/execution/batch-pool.md) |
| <a id="idempotent-attribute"></a> Idempotent (атрибут) | Атрибут для мутирующих запросов. | [Контракт](../reference/execution/retry.md) |
| <a id="withidempotencykey"></a> withIdempotencyKey() | Метод AbstractRequest. | [Контракт](../reference/execution/retry.md) |
| <a id="idempotencyheader"></a> idempotencyHeader | Параметр ClientConfig. | [Контракт](../reference/execution/retry.md) |

[Все термины](README.md).
