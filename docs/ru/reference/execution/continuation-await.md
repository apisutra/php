<!-- languages --> <a href="../../../en/reference/execution/continuation-await.md">English</a> · <a href="continuation-await.md">Русский</a> <!-- /languages -->
# Ожидание результата операции <a id="section-1"></a>

## ContinuationTokenExtractor <a id="section-2"></a>
```php
final class ExampleContinuationTokenExtractor implements ContinuationTokenExtractorInterface
{
    public function extract(ExecutionResult $result): ?string
    {
        $data = $result->data;
        if (!is_array($data)) {
            return null;
        }

        $token = $data['operationToken'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    continuationTokenExtractor: new ExampleContinuationTokenExtractor(),
);
```

Extractor задаёт единый способ извлечения continuation token для `resolved()`:
- `$request->send()->resolved()->continuationToken()`
- `$request->send()->resolved()->continuationTokenOrFail()`
- `$request->send()->continuationToken()` и `continuationTokenOrFail()` через `ResultHandle`

Если extractor не задан, токен считается отсутствующим (`null`).

## Provider Async Await defaults <a id="section-3"></a>

```php
use ApiSutra\Enums\Continuation\ContinuationMode;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    continuationTokenExtractor: new ExampleContinuationTokenExtractor(),
    continuationModeApplicator: new ProviderContinuationModeApplicator(),
    defaultContinuationMode: ContinuationMode::Auto,
    defaultPollRequest: GetAsyncResultRequest::class,
);
```

- `defaultContinuationMode` — дефолт режима provider-выполнения для запросов без runtime override.
- `defaultPollRequest` — poll-request по умолчанию для `awaitByToken()`/`awaitByTokenAs()`.
- `continuationModeApplicator` — провайдерный маппинг `ContinuationMode` в реальный протокол (`query/body/header`).
- `continuationStateResolver` — экземпляр `ContinuationStateResolverInterface` для явной
  готовности Pending/Ready/Failed. Применяется после `ContinuationResult::stateResolver`
  и встроенного resolver по непустому `unwrap`; для `awaitByTokenAs()` обязателен.

Подробный DX и контракты: [Provider Async Await](../../guides/recipes/continuation.md).

Пример defaults выше предполагает критерий в `ContinuationResult` запроса:
`stateResolver` или непустой `unwrap`. Для `awaitByTokenAs()`, а также ожидания
без такого объявления добавьте клиентский `continuationStateResolver`.

Унифицированный DX для long-running сценариев: токен продолжения читается
на уровне `resolved()` результата, а не через provider-specific helper в ресурсах.

## Что это такое <a id="section-4"></a>

`continuation token` — идентификатор операции, который провайдер возвращает
в стартовом ответе (например, `operationToken`, `taskId`, `jobId`), чтобы потом
получить статус или итог операции.

В `apisutra` токен извлекается pluggable-стратегией:
- `ContinuationTokenExtractorInterface`
- `ClientConfig::continuationTokenExtractor`
- DX-методы `ResolvedResultInterface`:
  - `continuationToken(): ?string`
  - `continuationTokenOrFail(): string`

Без extractor поведение безопасное и предсказуемое: `continuationToken()` вернёт `null`.

Этот гайд покрывает только получение token.
Полный async-await DX (mode/applicator/polling) описан отдельно:
[Provider Async Await](../../guides/recipes/continuation.md).

## Базовый пример <a id="section-5"></a>

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Result\ContinuationTokenExtractorInterface;
use ApiSutra\Result\ExecutionResult;
use Override;

final class ProviderContinuationTokenExtractor implements ContinuationTokenExtractorInterface
{
    #[Override]
    public function extract(ExecutionResult $result): ?string
    {
        $payload = $result->response?->json();
        if (!is_array($payload)) {
            return null;
        }

        $token = $payload['operationToken'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.provider.test',
    continuationTokenExtractor: new ProviderContinuationTokenExtractor(),
);
```

## Использование в прикладном коде <a id="section-6"></a>

```php
$handle = $client->operations()->start($request);

$token = $handle->continuationToken(); // ?string
$required = $handle->continuationTokenOrFail(); // string, иначе SdkException

// Эквивалентно через resolved():
$resolvedToken = $handle->resolved()->continuationToken();
```

Если нужно не только получить token, но и дождаться финала операции —
используйте:
- `$handle->await()` / `$handle->awaitAs(...)`
- `$client->continuation()->awaitByToken(...)`

Наличие token само по себе не определяет состояние операции. Ожидание дополнительно
требует `ContinuationResult::unwrap` либо resolver готовности. Ready гидратируется,
Pending продолжает polling, Failed завершает его даже при token. Подробности — в [контракте ожидания](../../guides/recipes/continuation.md).
Чтение HTTP JSON в примере работает и при `Returns`, когда `$result->data` уже DTO.

## Рекомендации для провайдерного SDK <a id="section-7"></a>

- Не хардкодьте ключи токена в ядре `apisutra`.
- Делайте extractor на стороне провайдерного пакета, где известен payload-контракт.
- Возвращайте `null`, если токен отсутствует или невалиден.
- `continuationTokenOrFail()` используйте в сценариях, где токен обязателен по контракту.

## Ошибки ожидания continuation <a id="section-8"></a>

`ContinuationAwaitException` находится в `ApiSutra\Exceptions\Continuation`
и наследует `SdkException`. Его `reason` различает:

| reason | Причина |
| --- | --- |
| `final_hydration_failed` | Ready-payload не преобразуется в финальный DTO, включая неверную форму |
| `final_not_ready` | Sync получил Pending |
| `continuation_token_missing` | Pending без token у результата без ошибки |
| `attempts_exhausted` | После последнего разрешённого poll результат остаётся Pending |
| `continuation_failed` | Resolver вернул Failed для результата без ошибки |

`attempts` — число оценённых ответов, `lastResult` — последний `ExecutionResult`
с HTTP-ответом. При `final_hydration_failed` previous содержит исходную
`HydrationException`, путь которой включает `unwrap`/path resolver. Неверная форма
Ready-payload даёт `unexpected_response_shape` с путём payload или `$`.
Это правило действует также при смене типа в кешированном `awaitAs()`.

`context()` и автоматический лог содержат reason, attempts, httpStatus, traceId
и вложенный hydration-контекст. Payload, token и значения полей туда не включаются;
исходный ответ доступен явно через `lastResult` при любом значении debug.
Failed и Pending без token доставляют исходное исключение failed-результата через
`throw()`; исключения resolver и конфигурации DTO не оборачиваются.
Неверная настройка ожидания остаётся `ContinuationConfigurationException`.

Критерии готовности, примеры и [правила готовности](continuation-await.md#section-10)
описаны в руководстве ожидания. Стартовый `send()`/`raw()` сохраняет поведение
result-first и `throwOnErrors`; ошибка await не заменяет его результат.

## Лимит, кеш и диагностика <a id="section-9"></a>

`ContinuationAwaitOptions(maxAttempts: 30, intervalMs: 1000)` ограничивает число
poll-запросов. Стартовый ответ этот лимит не расходует; после последнего poll
паузы нет. Поле ошибки `attempts` считает оценённые ответы, включая старт в Auto/Sync:
при `maxAttempts: 1` и двух Pending в Auto оно равно 2, в Async — 1.

Повторный `await()` или `awaitAs()` того же типа возвращает кешированный результат.
Другой тип в `awaitAs()` гидратируется из сохранённого Ready-payload без HTTP;
ошибка преобразования сохраняет прежний кеш. Все входы используют гидратор клиента.
Для собственного `ClientInterface` конструктор сервиса требует явный гидратор:
`new ContinuationService($client, $hydrator)`; допустим `Hydrator::default()`.

`ContinuationService::resolveFromStartResult()` возвращает `ContinuationOutcome`
с `value`, исходным `payload`, его `path`, `lastResult` и `attempts`.
`hydrateOutcome($outcome, $type)` преобразует тот же payload в другой тип.
Методы `awaitFromStartResult()`, `awaitByToken()` и `awaitByTokenAs()` возвращают значение.

Ошибки ожидания описаны в [руководстве ошибок](continuation-await.md#section-8).
HTTP-ответ доступен через `ContinuationAwaitException::lastResult` независимо от debug.
Автоматический лог включает безопасный `context()`, без payload и token.

## Явная готовность результата <a id="section-10"></a>

Объявите `unwrap` или resolver. Для результата в корне без обёртки нужен resolver,
явно возвращающий Ready. Отсутствующий/null `unwrap` не подменяется корневым payload.
Ошибка преобразования Ready немедленно даёт
`ContinuationAwaitException(final_hydration_failed)` с исходным `HydrationException`
в previous. Лимиты, отсутствие токена и Sync not-ready обрабатываются как ошибки
ожидания во время исполнения; `ContinuationConfigurationException` означает
неверную конфигурацию.

## Внешние правила финального DTO <a id="section-11"></a>

Гидратор клиента применяет `hydration` к Ready-payload и повторному `awaitAs()`.
Strict-ошибка оборачивается в `final_hydration_failed` сразу, даже при наличии token.
Путь финала дополняет `sourcePath`; при Ready без объявленного пути происхождение
Unavailable. [Диагностика внешних правил](../dto/diagnostics.md#section-3).

## Корреляция ожидания <a id="section-12"></a>

`ContinuationOutcome::trace` и `audit` описывают запуск ожидания; `lastResult`
остаётся последним реальным ответом. Await наследует trace старта, poll связан
с ожиданием через `parentExecutionId`. Самостоятельный awaitByToken создаёт корень
из client default или нового trace. Истёкший бюджет HTTP-старта не наследуется.

`ContinuationAwaitException::trace` и context содержат идентичность ожидания;
`attempts`, `reason`, `lastResult` и исходная причина сохраняются. Повторный cached
await того же типа не создаёт запуск. Cached awaitAs другого типа создаёт дочернюю
конвертацию без HTTP; ошибка оставляет прежний outcome доступным.
[Общая схема корреляции](../results/observability.md#section-5).

## Внутреннее исполнение poll <a id="canonical-poll"></a>

Poll использует исполнитель клиента с Single, не меняя исходный запрос и его настройки.
Он получает trace ожидания, собственный HTTP-бюджет и клиентскую политику meta/локали/фабрики.
Публичный throwOnErrors не прерывает решение resolver о Pending. Окончательная ошибка
failed-результата выдаётся после терминального события логической области через
[выбор исключения](../results/exceptions.md). Начальный публичный send остаётся отдельной
границей и может бросить исключение до начала await.
