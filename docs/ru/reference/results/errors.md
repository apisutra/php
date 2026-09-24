<!-- languages --> <a href="../../../en/reference/results/errors.md">English</a> · <a href="errors.md">Русский</a> <!-- /languages -->
# Ошибки и политика доставки <a id="section-1"></a>

Язык собственных сообщений задаёт [LocalizationConfig](../client/localization.md);
по умолчанию английский, встроен русский. Технические коды и сторонние тексты сохраняются.

## throwOnErrors <a id="section-2"></a>
```php
use ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    throwOnErrors: true,
);
```

Необязательная [фабрика исключений результата](exceptions.md) задаёт класс ошибки
для `dataOrFail()`, `throw()` и `throwOnErrors`. Она вызывается после внутренних
решений об auth/retry и не меняет исходную классификацию ошибок. `errorMapper` ниже
меняет представление ошибки, а не выбрасываемое исключение.

Правило применяется к окончательной публичной выдаче, включая batch, pool,
paginator и sendInContext; значение по умолчанию — false.

| Окончательный статус | false | true |
| --- | --- | --- |
| SUCCESS / PARTIAL | Return / fulfill | Return / fulfill |
| FAILED | Return / fulfill результата | Throw / reject |

Внутреннее исполнение всегда возвращает канонический результат. FailStrategy и
готовность continuation определяются до выдачи и не зависят от этой настройки.

## ErrorContextFactory <a id="section-3"></a>
```php
use ApiSutra\VO\Errors\ClientError;

final readonly class ExampleErrorContext
{
    public function __construct(
        public ?string $traceId,
        public ?string $target,
    ) {}
}

final class ExampleErrorContextFactory implements ErrorContextFactoryInterface
{
    public function make(ClientError $error): ?object
    {
        return new ExampleErrorContext(
            traceId: $error->context['traceId'] ?? null,
            target: $error->context['target'] ?? null,
        );
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    errorContextFactory: new ExampleErrorContextFactory(),
);
```

Если `errorContextFactory` не задана — `errorContext()` вернёт `null`,
`errorContexts()` — пустой массив. Raw‑контекст доступен через `ClientError::context`.

Системные ключи контекста: `traceId`, `httpStatus`, `requestClass`, `providerCode`.
Порядок merge: system → `RequestError.context` → контекст из `ClientErrorMapper`.

**Поле `providerTraceId`**: добавляйте в typed context **только** если провайдер подтверждённо
возвращает trace-id в ответах на ошибки. См. [Методология провайдера](../../start/create-sdk.md).

## ClientResponseFactory / ErrorMapper <a id="section-4"></a>

```php
use ApiSutra\Response\ClientResponse;
use ApiSutra\Response\ClientResponseFactoryInterface;
use ApiSutra\VO\Errors\ClientError;
use ApiSutra\VO\Errors\ClientErrorMapperInterface;
use ApiSutra\VO\Errors\RequestError;

final class ExampleClientResponseFactory implements ClientResponseFactoryInterface
{
    public function make(ResolvedResultInterface $result): ClientResponse
    {
        return new ClientResponse(status: 200, headers: [], body: 'ok');
    }
}

final class ExampleClientErrorMapper implements ClientErrorMapperInterface
{
    public function map(RequestError $error): ClientError
    {
        return new ClientError(
            providerCode: $error->response?->status,
            sdkCode: $error->code,
            message: $error->message,
        );
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    responseFactory: new ExampleClientResponseFactory(),
    errorMapper: new ExampleClientErrorMapper(),
);
```

Если `responseFactory` не задан, используется дефолтный `ClientResponseFactory`.
`errorMapper` опционален и нужен только для кастомного формата ошибок.

`ClientResponseFactory` превращает `ResolvedResult` в удобный для приложения ответ.
`ClientErrorMapper` контролирует формат ошибок и итоговый HTTP‑статус.

## Локальный rate-limit и HTTP 429 <a id="section-5"></a>

Локальная квота даёт `rate_limited` с `reason=local_rate_limit_exceeded` и `retryAfter`
в секундах. Если HTTP ещё не было, response равен null. Сбой store или атомарного backend даёт `execution_error`
с `reason=rate_limit_backend_error`. Обе причины останавливают HTTP retry. Реальный
ответ 429 обрабатывается HTTP-политикой. Подробный [контракт и примеры](../execution/rate-limit.md#section-5).

`RequestException::$response` имеет тип `?ProviderResponse`, поэтому обработчики
всей этой иерархии должны проверять наличие ответа. `catch (RateLimitException)`
и retryAfter сохранены. При локальном отказе после предыдущей HTTP-попытки её ответ
доступен в результате и в exception.lastResponse; exception.response остаётся null.
[Выбор квот](../execution/rate-limit.md#section-7).

## Политика ошибок <a id="section-6"></a>
Можно переопределять поведение в `AbstractRequest` или `AbstractClient`:
- `hasRequestFailed()`
- `shouldRetry()`
- `getRequestException()`

Порядок разрешения: **request → client → default**.

Дефолтная логика:
- `hasRequestFailed()` → HTTP статус `>= 400`
- `shouldRetry()` → `false`
- `getRequestException()` → `null`

Переопределяйте, если у провайдера есть нестандартный сигнал ошибки
(например, `status` в body) или нужен свой критерий retry/исключений.

При неуспешном HTTP-ответе выбранное политикой исключение задаёт также
`RequestError.message`, включая локализуемый дескриптор сообщения. Если исключения
нет, используется сообщение ответа. Объявление секретных полей диагностики не
подменяет эти сообщения; безопасный текст запрос задаёт через `getRequestException()`.

## ClientResponse и маппинг ошибок <a id="section-7"></a>
Для внешних API удобнее преобразовать результат:
- `ResolvedResultFactoryInterface` → `ResolvedResultInterface`
- `ClientResponseFactoryInterface` → `ClientResponse`
- `ClientErrorMapperInterface` → `ClientError`
- `ClientErrorFactory` → единый маппинг для `ClientResponse` и DX‑методов

## Control‑flow исключения <a id="section-8"></a>
- `EarlyReturnException` — завершить пайплайн успехом без HTTP
- `RetryableException` — форсировать retry на уровне обработки

## Классификация ошибок JSON и исполнения <a id="section-9"></a>

| Сбой | SDK code |
| --- | --- |
| Кодирование исходящего JSON | `serialization_error` |
| Разбор непустого JSON-ответа | `response_decoding_error` |
| Приведение данных ответа к DTO | `hydration_error` |
| Пользовательский hook | `hook_error` |
| PSR-18 network failure | `connection_failed` |
| Подтверждённый timeout | `timeout` |
| PSR-18 request failure | `invalid_request` |
| Прочий PSR-18 client failure | `transport_error` |
| Локальное чтение, запись или публикация файла | `file_transfer_error` |
| Конфигурация | `configuration_error` |
| Неизвестное исключение исполнения | `execution_error` |

Подтверждённые Guzzle/cURL ошибки отправки/чтения 55/56 и пустого ответа 52
классифицируются как `connection_failed`, код 28 — как `timeout`.
Сохраняется original exception в previous; текст ошибки не используется для
угадывания сетевого кода. PSR-18 путь не требует Guzzle Client.

### Состояние отправки <a id="section-10"></a>

`TransportException::transmissionState` — enum `TransmissionState` из
`Enums\Http`, относящийся к попытке. По умолчанию значение `Unknown` (`unknown`):
исход отправки неизвестен. `NotSent` (`not_sent`) допустим только при доказательстве,
что запрос не был отправлен. Собственный транспорт отвечает за это утверждение.
Timeout, пустой ответ и обрыв чтения/записи не доказывают отсутствие отправки.
Generic PSR network failure и один DNS errno также не дают такой гарантии.

Для транспортного сбоя или локального deadline смотрите
`$result->errors->first()?->context['transmissionState']`:
здесь состояние накоплено по всем попыткам текущего запроса. Неопределённая ранняя
попытка не исчезает после более позднего not_sent. Deadline до первой отправки
даёт not_sent; после возможной отправки — unknown. Auth и дочерние запросы имеют
свои состояния, это не статус всей бизнес-операции приложения. В исключении
ExecutionDeadlineException pipeline сохраняет накопленное состояние текущего запроса.

Диагностика не разрешает автоматический POST retry и не доказывает, выполнил ли
сервер операцию. После потери ответа приложение может использовать предусмотренную
провайдером проверку результата или ключ идемпотентности.

### Декодирование ответа <a id="section-11"></a>

`SerializationException`, `ResponseDecodingException` и `HydrationException` находятся
в `Exceptions\Serialization`. Ошибки стандартного JSON-кодека сохраняют
`JsonException` в `previous`; HTTP-ответ ошибки разбора остаётся в `ExecutionResult::response`.
Проверка pipeline применяется к непустому `application/json`, MIME с суффиксом
`+json` и ответу без `Content-Type`; успешный статус 204 исключён из разбора.
Значения `null`, пустого тела и текста описаны в
[контракте успешного ответа](handles.md#section-3).
Публичный `ProviderResponse::jsonStrict()` выполняет строгий разбор по явному вызову;
существующий `json()` сохраняет permissive-поведение для совместимости, в том числе
для пользовательских обработчиков HTTP-ошибок обычных строковых ответов.
Потоковый download требует явного чтения `ProviderResponse.stream`: его `json()`
и `jsonStrict()` дают `configuration_error`. Обработка расширений вне download
сохраняется в Auto; явный [RawResponse](../attributes/response.md#section-7)
обходит обработчики формата и JSON. Подробнее — [файловые ответы](../../guides/recipes/files.md).

### Ошибки структуры и диапазона чисел <a id="section-12"></a>

Нарушения контрактов полей DTO, строгий `Returns::unwrap`, JsonCast и защита int возвращают `hydration_error` с
`HydrationException`. Для этих ошибок `RequestError::context` и свойства исключения
содержат `reason`, `path`, `expected`, `actual`:

| reason | Смысл |
| --- | --- |
| `response_type_mismatch` | Итоговое значение нарушает активный Returns; [сообщения и границы](exceptions.md). |
| `unwrap_path_missing` | Указанный путь отсутствует; actual — `missing`. |
| `unexpected_response_shape` | По пути найден null/scalar вместо данных объявленного DTO либо непустой list вместо одиночного Nested. |
| `integer_out_of_range` | Число не помещается в int; actual — тип исходного значения. |
| `required_field_missing` | Обязательное поле отсутствует. |
| `null_not_allowed` | Итоговое значение null не допускается объявленным типом. |
| `invalid_field_type` | Значение после casts не подходит типу поля или параметра. |
| `invalid_json` | JsonCast не может разобрать вложенную JSON-строку. |
| `invalid_datetime` | Дата не принята выбранной политикой Throw; expected включает формат. |
| `unknown_nested_variant` | Nested в режиме Error не нашёл вариант; expected содержит объявленные допустимые варианты. |

Например, `path=data.item.id`, `expected=int`, `actual=string` указывает поле с
переполнением. Для вложенных DTO используются имена свойств, для коллекций —
порядковый индекс (`items[1].id`), без включения внешних ключей и значений в диагностику.
Это сведения о новых проверках; у остальных HydrationException эти свойства могут быть null.

Структурированные ошибки `DefaultValue` provider также получают имя текущего поля:
например, `data.child.count`. Правила построения пути —
в [контракте provider](../dto/defaults.md#section-9).

Исходный HTTP-ответ остаётся в результате. Ошибка гидратации не запускает новый HTTP
retry. `raw()`/`resolved()` сообщают ошибку, `dataOrFail()` и `throwOnErrors` выбрасывают
исключение; sync и promise API используют один контракт.
Поведение unwrap описано в [Returns](../attributes/response.md#section-5),
числовые типы — в [сериализации](../dto/scalars.md#section-4).

Подробные поля исключения описаны в [диагностике DTO](../dto/diagnostics.md#section-4).

### Ошибки файлов и транспорта <a id="section-13"></a>

`FileTransferException` из `Exceptions\Files` содержит стадию, `bytesWritten` и
`partial`; эти поля доступны и в error context. При ошибке финальной записи
сохраняется HTTP-статус принятого ответа. Локальная ошибка не вызывает HTTP retry,
даже если в `retryExceptions` указан общий `Throwable`. При deadline во время
копирования код остаётся `timeout`, с данными о частичной записи.

Известные транспортные исключения нормализуются до решения retry. Исходное исключение
доступно в `previous`; явно настроенный исходный класс в `retryExceptions` продолжает
учитываться. Timeout не определяется по тексту сообщения: поддерживается собственный
`TimeoutException`, общий бюджет повторов и подтверждённый cURL errno 28 у Guzzle
ConnectException. Наличие Guzzle HTTP Client для остальных транспортов не требуется.

После исчерпания повторов последний HTTP-ответ проходит обычный error mapping.
Например, 503 остаётся `service_unavailable`, включая ответ с HTML или испорченным JSON.
Для 400 используется `bad_request`, для остальных немаппируемых 4xx — `client_error`;
ответ и его raw body сохраняются. Строковый `message` из JSON используется как сообщение,
иначе применяется `HTTP <status>`. Если последняя HTTP-попытка завершилась сетевым сбоем,
ответ предыдущей попытки не подставляется вместо отсутствующего текущего ответа.

Произвольное исключение hook не становится сетевым и не вызывает сетевой retry.
Оно сохраняется в `ExecutionResult::exception` без замены исходного объекта.
Типизированные HTTP/configuration-исключения сохраняют свои коды. `EarlyReturnException`
работает и на стадии AfterResponse, завершая выполнение успехом; `RetryableException`
сохраняет специальную управляющую семантику. Ошибки конфигурации DTO не маскируются
как ошибки данных гидратации.

Эти правила одинаковы для sync, promise API и batch: `throwOnErrors` меняет способ
доставки исключения, а не причину сбоя. Политика идемпотентности и replay потоков
в этой поставке не меняется.

## Исчерпание общего бюджета <a id="section-14"></a>

`ExecutionDeadlineException` является `TimeoutException`. В `raw()->errors` код —
`timeout`, контекст содержит `reason: execution_deadline_exceeded` и `stage` остановки.
Это окончательная ошибка выполнения, даже если последний HTTP-ответ имел статус 200
или 503. Доступный реальный ответ сохраняется в диагностике; до первого HTTP ответа нет.
`raw()`/`resolved()` показывают ошибку, `dataOrFail()` и `throwOnErrors` выбрасывают её. В исключении
`response` сохраняет доступный ответ, в `getPrevious()` — исходную причину.
Таймаут отдельной попытки допускает безопасный retry, общий deadline — нет.
Полный контракт — [Timeouts & Delay](../execution/deadlines.md).

### Недоступная валидация <a id="section-15"></a>

Если объявлены `#[Validate]`, но совместимая фабрика недоступна или provider не смог
её предоставить, запрос завершается с `configuration_error` до HTTP. Список
`validationErrors` пуст: данные не были проверены. Неверные данные при работающей
фабрике дают `validation_failed` с ошибками полей.
Прямые `validate()`/`isValid()`/`errors()` при недоступном движке выбрасывают
`ConfigurationException`. [Контракт](../client/validation.md#section-11).

При отказе обновления авторизации после основного 401 сохраняются оба ответа:
основной — в `response`, refresh — в `AuthRefreshFailedException::dependencyResult`.
Автоматический context содержит `reason=auth_refresh_failed`, без credentials и
полного результата зависимости. [Контракт восстановления](../auth/tokens.md#section-11).

Batch/pool сохраняют классификацию и фактический ответ HTTP, decoding, hydration,
configuration и hook ошибок независимо от throwOnErrors. `connection_failed` означает
подтверждённую сетевую ошибку; неизвестное исключение даёт `execution_error`.
Существующее различие стратегий по выбрасыванию исключений сохраняется.
Ошибка [recording](../testing/fixtures.md#section-3) может сопровождаться HTTP 200:
основная операция уже выполнена, повторять её как сетевой сбой нельзя.

Локальный отказ cooldown имеет код rate_limited и reason server_cooldown_active, отдельно от
local_rate_limit_exceeded. См. [CooldownException, время и контекст ответа](../execution/cooldown.md#errors).
