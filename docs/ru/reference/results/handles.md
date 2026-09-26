<!-- languages --> <a href="../../../en/reference/results/handles.md">English</a> · <a href="handles.md">Русский</a> <!-- /languages -->
# ResultHandle и представления результата <a id="section-1"></a>

`send()` возвращает `ResultHandle`. Его `raw()` даёт `ExecutionResult`, `resolved()` —
прикладное представление результата. `dataOrFail()` извлекает данные или выбрасывает
ошибку при статусе `FAILED`; `PARTIAL` возвращает данные без исключения. [Учебный SDK](../../../example/sdk/run.php) выполняет оба сценария.

Активный `Returns` гарантирует тип конечного успешного значения. Сообщения
операций и собственные исключения клиента описаны в [отдельном контракте](exceptions.md).

## Выбрать уровень результата <a id="section-2"></a>

| API | Результат и назначение |
| --- | --- |
| `request->send()` / `client->send($request)` | ResultHandle для дальнейшего чтения |
| `request->resolved()` / `handle->resolved()` | ResolvedResultInterface: данные, статус и ошибки |
| `request->dataOrFail()` / `handle->dataOrFail()` | Данные либо исключение при FAILED |
| `handle->raw()` | ExecutionResult, включая meta/audit/debug; не строка HTTP |
| `request->sendAsync()` / `client->sendAsync($request)` | `ResultPromiseInterface<ResultHandle>` |
| `request->resolvedAsync()` | `ResultPromiseInterface<ResolvedResultInterface>` |
| `client->response($resolved)` | ClientResponse для передачи ответа приложению |

Promise не гарантирует неблокирующий I/O; [фактическая семантика](../execution/transport.md).
`await()` и token-only ожидание относятся к [операции провайдера](../execution/continuation-await.md).
Для намеренного чтения тела без декодирования нужен RawResponse, а не raw().

## Успешный ответ без DTO <a id="section-3"></a>

Для обычного запроса без DTO, пагинации, `#[Download]` и обработчика расширения
`ExecutionResult::data`, `resolved()->data()` и `dataOrFail()` возвращают:

| Ответ | Значение |
| --- | --- |
| HTTP 204 или тело длиной 0 байт | `null` |
| JSON `null` | `null` |
| JSON `false`, `0`, строка | Соответствующее значение без замены на массив |
| JSON-массив или объект | PHP-массив; `{}` и `[]` дают `[]` |
| Непустой `text/plain` | Исходная строка, включая пробелы и переводы строк |
| Другой явно указанный не-JSON Content-Type | Исходная строка, даже если тело похоже на JSON |
| Непустой ответ без `Content-Type` | Строго разбирается как JSON |

JSON определяется по `application/json` и суффиксу `+json`; регистр MIME и параметры
вроде `charset` не влияют на выбор. Некорректный JSON, включая тело только из пробелов,
даёт `response_decoding_error` с исходным HTTP-ответом. HTTP 204 не разбирается даже
при наличии тела. `null` в успешном результате допустим: проверяйте `isSuccess()`,
а не наличие данных; `dataOrFail()` возвращает такой `null` без исключения.

Неизвестные явно указанные MIME возвращают исходную строку без DTO. Для декодирования
специального формата зарегистрируйте расширение. `#[Download]` возвращает
`FileResponse`; выбранный обработчик расширения получает исходный ответ.
Если он возвращает `null`, применяется стандартный разбор.

При объявленном DTO пустое тело/JSON `null` передаётся в гидрацию как
пустой набор полей; результат зависит от обязательных полей и defaults DTO. Для
пагинации сохраняется контракт массива. Скаляры вместо DTO или данных пагинации
дают `hydration_error`.
Настоящий JSON array, включая `[]`, отвергается на входе DTO. JSON `{}` остаётся
объектом и проходит к проверкам полей. [Форма и локальные исключения](../dto/shapes.md#section-3).
Непустой ответ не-JSON формата для DTO/пагинации без обработчика расширения даёт
`response_decoding_error`, reason `unsupported_response_content_type`.

`BeforeHydrate` сохраняет сигнатуру массива и вызывается только для массивов.
Для обычных `null`, скаляров и текста он пропускается; `AfterResponse` и `AfterHydrate`
продолжают вызываться. Подробнее: [hooks](../extensions/hooks.md#section-6)
и [классификация ошибок](errors.md#section-9).

Для намеренного чтения тела без декодирования используйте необязательные
[`#[RawResponse]` или `withRawResponse()`](../attributes/response.md#section-7).
`ResultHandle::raw()` сам по себе возвращает ExecutionResult и не отключает JSON.

## ExecutionResult <a id="section-4"></a>

`$execution = $handle->raw()` возвращает полный результат выполнения. Это readonly-объект
с публичными свойствами; данные и диагностика доступны независимо от выбранного
прикладного представления. Его `status` описывает выполнение SDK, а HTTP-код находится
в `$execution->response?->status`, если HTTP-ответ получен.

| Свойство | Что содержит |
| --- | --- |
| `data` | Подготовленные данные: DTO, коллекция, массив, скаляр или null в зависимости от операции |
| `status` | `ResultStatus::SUCCESS`, `PARTIAL` или `FAILED` |
| `errors` | `ErrorCollection` с исходными ошибками выполнения и транспорта |
| `validationErrors` | Отдельный массив ошибок проверки входных полей |
| `exception` | Исходное исключение либо null |
| `response` | Полученный `ProviderResponse` либо null; доступен без включения debug |
| `meta` | Метаданные операции, например пагинации или составного результата |
| `nested` | Массив результатов дочерних запросов |
| `requestClass` | Класс запроса либо null |
| `traceId` | Идентификатор трассировки либо null |
| `audit`, `debug` | Хронология исполнения и необязательный debug-снимок; [правила записи и экспорта](observability.md) |

Методы `isSuccess()`, `isPartial()` и `isFailed()` проверяют статус, `hasData()` —
наличие значения, отличного от null. `hasErrors()` проверяет errors; для отдельного
списка validationErrors есть `hasValidationErrors()` и `validationErrorFor($field)`.
`throw()` выбрасывает сохранённое исключение либо SdkException только при `FAILED`;
при остальных статусах возвращает этот же ExecutionResult.

Для быстрого просмотра подготовленного HTTP-запроса:

- `requestDebug()` -> `?array` (`method`, `url`, `headers`, `bodyRaw`, `hasStream`, `oneOf`)
- `requestDebugJson()` -> `?string` (JSON снимка)

По умолчанию `requestDebug*` маскирует чувствительные заголовки
(`Authorization`, `Cookie`, `X-Api-Key` и т.д.).

## ResultHandle <a id="section-5"></a>

Handle предоставляет готовый результат без повторной отправки. Получите его через
`send()` или `sendAsync()->wait()`, затем используйте raw/resolved/dataOrFail.
Новый send/sendAsync запускает отдельное исполнение.

```php
$handle = $request->send();

$raw = $handle->raw();          // ExecutionResult
$resolved = $handle->resolved();// ResolvedResultInterface
$data = $handle->dataOrFail();  // бросит исключение при FAILED
$token = $handle->continuationToken(); // ?string
$tokenStrict = $handle->continuationTokenOrFail(); // string или SdkException
$final = $handle->await();      // unified async-await (если настроен continuation-контракт)

$request = $raw->requestDebug();     // ?array
$requestJson = $raw->requestDebugJson(); // ?string

// Sugar через ResultHandle:
$request2 = $handle->requestDebug();      // ?array
$requestJson2 = $handle->requestDebugJson(); // ?string
```

## ResolvedResult <a id="section-6"></a>

`$resolved = $handle->resolved()` возвращает `ResolvedResultInterface`; стандартная
реализация — `ResolvedResult`. Это представление для приложения: данные и статусы
читаются методами, ошибки преобразуются в удобные `ClientError`.

| Метод | Что возвращает или проверяет |
| --- | --- |
| `data()` | Подготовленные данные, включая DTO; сам метод не выбрасывает ошибку из-за статуса FAILED |
| `isSuccess()`, `isPartial()`, `isFailed()` | Статус выполнения SDK |
| `hasData()` | Данные отличаются от null; успешный ответ с null вернёт false |
| `hasErrors()`, `errors()` | Наличие и коллекцию исходных ошибок выполнения; validationErrors доступны через `result()` |
| `error()`, `errorViews()` | Первую прикладную ошибку или полный массив ClientError |
| `message()` | Краткое сообщение об ошибках либо null |
| `result()` | Исходный ExecutionResult со всеми полями и диагностикой |

В стандартном представлении `$resolved->data()` — те же данные, что и
`$execution->data`, а `$resolved->result()` — тот же объект, что и `$handle->raw()`.
При `PARTIAL` данные могут сосуществовать с ошибками; проверяйте статус отдельно.
Собственное представление подключается через [фабрику](#section-10).

### ResolvedResult: удобное чтение ошибок <a id="section-7"></a>
```php
$resolved = $request->send()->resolved();

$error = $resolved->error();          // ?ClientError (первая ошибка)
$code = $resolved->errorCode();       // ?string
$message = $resolved->errorMessage(); // ?string
$status = $resolved->errorStatus();   // ?int
$providerTraceId = $resolved->errorProviderTraceId(); // ?string
$token2 = $resolved->continuationToken(); // ?string

// Полный список ошибок (batch/pool/composite)
$views = $resolved->errorViews();     // array<ClientError>

// Типизированный контекст (если задана фабрика)
$context = $resolved->errorContext();  // ?object
$contexts = $resolved->errorContexts();// array<object|null>
```

`errorCode()` возвращает первый доступный код по приоритету:
`appCode → clientCode → providerCode → sdkCode`.

`ClientError` находится в `ApiSutra\VO\Errors`.
Raw‑контекст всегда доступен через `$resolved->error()?->context`.
`errorProviderTraceId()` вернёт `null`, если typed‑контекст отсутствует
или не содержит `providerTraceId`.

**`providerTraceId` в контексте:** добавляйте это поле **только** при подтверждённой поддержке
трассировки со стороны провайдера (документация, реальные ответы). Без подтверждения — не вводите;
подробнее: [Методология провайдера](../../guides/sdk/first-operation.md#section-1).

Примечание: `errorRetryable()` и `errorCategory()` по умолчанию возвращают `null`
— их нужно определять на уровне SDK провайдера.

`continuationToken()` и `continuationTokenOrFail()` работают через
`ClientConfig::continuationTokenExtractor`. Если extractor не задан,
`continuationToken()` вернёт `null`.

### Типизированный контекст ошибок <a id="section-8"></a>
Чтобы получить typed‑контекст, задайте `errorContextFactory` в `ClientConfig`.
Если фабрика не задана — `errorContext()` вернёт `null`, а `errorContexts()` — пустой массив.

### Системные ключи context <a id="section-9"></a>
Ядро стандартизирует набор ключей:
- `traceId`
- `httpStatus`
- `requestClass`
- `providerCode` (после маппинга в `ClientError`)

Ключи доступны как `SystemErrorContextKeys` в `ApiSutra\VO\Errors`.

Порядок merge:
1) системный context ядра
2) `RequestError.context`
3) контекст из `ClientErrorMapper` (last‑write‑wins)

## Фабрики представлений <a id="section-10"></a>

`ClientConfig::resolvedResultFactory` задаёт `ResolvedResultFactoryInterface` с
`make(ExecutionResult): ResolvedResultInterface`. Без override используется
`ResolvedResultFactory`. Свой результат должен сохранять полный интерфейс, включая
статус, ошибки и доступ к исходному ExecutionResult.

[Рецепт: собственный результат и фабрика](../../guides/recipes/custom-result.md)
показывает полное делегирование, подключение к клиенту и типизацию IDE на исполняемом примере.
Стандартный `ResolvedResult` — `final`; его можно обернуть реализацией интерфейса.
При своей фабрике зависимости маппинга ошибок, контекста и token передаются ей явно.

`responseFactory` задаёт `ClientResponseFactoryInterface` с
`make(ResolvedResultInterface): ClientResponse`. Без override стандартный ответ
даёт 200 для success, 207 для partial и статус, определённый error mapper, для failure.
Если меняется только представление ошибок, достаточно `errorMapper`; собственная
фабрика всего результата не требуется. [Ошибки и маппинг](errors.md).

Для HTTP-ответа приложения Laravel используется
`ApiSutra\Contracts\Interfaces\Response\ClientResponseAdapterInterface`.
[Контракт адаптера](https://github.com/apisutra/laravel/blob/master/docs/ru/reference/integrations/laravel.md#section-9).

Частичный результат, например после пагинации или составного исполнения, может
содержать одновременно данные и ошибки: проверяйте `isPartial()` и `errors()`.
Доступ к raw ответу через debug требует `debug: true`; безопасный экспорт описан
в [observability](observability.md).

## Provider ResultMetaExtractor <a id="section-11"></a>
```php
use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResultMetaExtractorInterface;

final readonly class ProviderEnvelopeMeta implements ResultMeta
{
    public function __construct(
        public ?int $resultCode,
        public ?string $resultMessage,
        public ?string $operationToken,
    ) {}
}

final class ExampleResultMetaExtractor implements ResultMetaExtractorInterface
{
    public function extract(ExecutionResult $result): ?ResultMeta
    {
        $payload = $result->response?->json();
        if (!is_array($payload)) {
            $payload = $result->errors->first()?->response?->json();
        }

        if (!is_array($payload)) {
            return null;
        }

        $code = $payload['resultCode'] ?? null;
        $message = $payload['resultMessage'] ?? null;
        $token = $payload['operationToken'] ?? null;

        if (!is_int($code) || !is_string($message) || !is_string($token)) {
            return null;
        }

        return new ProviderEnvelopeMeta(
            resultCode: $code,
            resultMessage: $message,
            operationToken: $token,
        );
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    resultMetaExtractor: new ExampleResultMetaExtractor(),
);
```

Назначение:
- вынести технические envelope-поля (`resultCode/resultMessage/operationToken`) из endpoint DTO;
- централизованно заполнить `ExecutionResult->meta` для обычных запросов.

Инварианты применения:
- если `ExecutionResult->meta` уже заполнена (pagination/batch/composite), extractor не перезаписывает её;
- если extractor вернул `null`, результат остаётся без изменений;
- если `resultMetaExtractor = null`, результат остаётся без изменений.
- `ExecutionResult->response` доступен независимо от `debug=true/false`, поэтому extractor
  не должен зависеть от debug-режима.

## Корреляция результата <a id="section-12"></a>

`ExecutionResult::trace` содержит `traceId`, `executionId` и `parentExecutionId`.
Локализация и чтение raw/resolved не меняют идентичность; batch/pool сохраняют полный
snapshot дочерней ошибки перед throw.
[Trace, audit и логи](observability.md#section-5).

## Жизненный цикл метаданных <a id="meta-lifecycle"></a>

Extractor вызывается один раз для результата запроса без meta: SUCCESS, PARTIAL или
FAILED, включая страницы, refresh авторизации и poll. Существующая meta не заменяется.
Самостоятельные batch/pool и прямой paginator не добавляют вызов extractor для агрегата.
Сбой extractor превращает SUCCESS/PARTIAL в ошибку; у существующего FAILED добавляет
вторичную ошибку без замены исходного exception, ответа и детей. Локализация и
терминальная диагностика следуют за извлечением meta, до публичной выдачи.

Async-выдача, вывод типов и цепочки: [типизированные промисы](promises.md).
