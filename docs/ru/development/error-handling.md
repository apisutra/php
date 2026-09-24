<!-- languages --> <a href="../../en/development/error-handling.md">English</a> · <a href="error-handling.md">Русский</a> <!-- /languages -->
# Доставка ошибок в ядре <a id="section-1"></a>

Краткое описание того, как SDK обрабатывает ошибки и формирует результат.

## Где могут возникнуть ошибки <a id="section-2"></a>
- Валидация запроса (локальная)
- Транспорт (HTTP/сеть)
- Hydration (DTO/формат ответа)
- Бизнес‑ошибки провайдера

## Стратегии <a id="section-3"></a>
- По умолчанию SDK возвращает `ExecutionResult` со статусом и ошибками.
- При `throwOnErrors = true` — исключение пробрасывается наверх.
- `ClientErrorFactory` + `ClientErrorMapper` отвечают за маппинг ошибок:
  - в `ClientResponseFactory`
  - в DX‑методах `ResolvedResult` (`error()`/`errorViews()`)
- `ErrorContextFactoryInterface` добавляет типизированный контекст для DX (`errorContext()`).
- Системный context ядра: `traceId`, `httpStatus`, `requestClass`, `providerCode`.

Явный `await()` выбрасывает ошибки ожидания независимо от `throwOnErrors`.
`ContinuationAwaitException` сохраняет последний результат и причину ошибки;
неудачная гидратация Ready-payload не превращается в следующий poll.
См. [ожидание](../guides/recipes/continuation.md).

При внешних правилах HydrationException разделяет DTO-путь и исходный JSON Pointer.
`context()` сохраняет точные данные для результата, `logContext()` маскирует
неизвестные ключи источника в автоматическом логе.
[Границы диагностики](../reference/dto/diagnostics.md#section-3).

## Переопределения <a id="section-4"></a>
`AbstractRequest` и `AbstractClient` могут переопределять:
- `hasRequestFailed()`
- `shouldRetry()`
- `getRequestException()`

Внутреннее исполнение возвращает канонические результаты независимо от публичного
throwOnErrors. Окончательная выдача следует [контракту исключений](../reference/results/exceptions.md);
[владельцы исполнения](execution.md) завершают диагностику до выбора исключения.
