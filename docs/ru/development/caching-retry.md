<!-- languages --> <a href="../../en/development/caching-retry.md">English</a> · <a href="caching-retry.md">Русский</a> <!-- /languages -->
# Кеш, retry и квоты в ядре <a id="section-1"></a>

Короткий обзор кеширования и повторных попыток.

## Кеширование <a id="section-2"></a>
- Кеш включается через `CacheConfig` в `ClientConfig`.
- Поддерживаются режимы: Enabled / Disabled / ReadOnly / WriteOnly.
- Ключ строится из подготовленного запроса и области кеша; traceId не разделяет записи.
  [Контракт ключа](../reference/execution/cache.md).
- Сохраняется HTTP-ответ, а не DTO. На cache hit клиент применяет собственный гидратор
  и текущий набор правил; набор не входит в ключ HTTP cache.

Отдельный [кеш метаданных](attributes.md#section-4) ускоряет гидратацию и
сериализацию, сохраняя изоляцию объектных defaults и аргументов атрибутов.

## Retry <a id="section-3"></a>
- Управляется `RetryConfig` и политикой `shouldRetry()`.
- Поддерживает backoff‑стратегии и jitter.
- Может учитывать `Retry-After`.

## Rate limiting <a id="section-4"></a>
- Лимиты задаются в `RateLimitConfig`.
- Поведение при превышении определяется `RateLimitBehavior`.

## Совместный учёт квот <a id="section-5"></a>

Общая квота клиента и собственная квота операции разрешаются одним набором перед
каждой HTTP-попыткой. RateLimiter организует Wait/Throw и общий budget; backend
только атомарно принимает или отклоняет набор. По умолчанию состояние локальное
и окна измеряются monotonic clock. PSR-16 сохранён только для одной квоты.
[Контракт](../reference/execution/rate-limit.md), [Redis](../reference/integrations/redis.md).

`CacheManager`, `AuthBindingResolver`, `AuthHandler` и подключение auth в AbstractClient
читают общий backend только из `ClientConfig.cacheConfig?->store`. Атрибут Cache
копирует блок через with(), сохраняя store/identity/locks; CacheExecutionState хранит выбранный store одного выполнения.
У метаданных, rate limiting и отдельных расширений остаются собственные настройки кеша.
