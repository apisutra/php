<!-- languages --> <a href="../../../en/reference/attributes/response.md">English</a> · <a href="response.md">Русский</a> <!-- /languages -->
# Атрибуты ответа <a id="section-1"></a>

## Сигнатуры и targets <a id="section-2"></a>

Имена классов относятся к `ApiSutra\Attributes\Response`.
Сигнатуры показывают параметры и defaults конструктора; target указывает допустимое
место атрибута. Поведение и приоритеты описаны в тематических ссылках ниже.

| Атрибут | Target | Конструктор |
| --- | --- | --- |
| ContinuationResult | CLASS | `ContinuationResult(string $finalType, ?string $unwrap = null, ?string $pollRequest = null, ?ContinuationMode $defaultMode = null, ?string $stateResolver = null)` |
| Download | CLASS | `Download()` |
| RawResponse | CLASS | `RawResponse()` |
| Returns | CLASS | `Returns(string $response, ?string $unwrap = null, ?string $type = null, ?string $mismatchMessage = null, string\|false\|null $hydrator = null)` |

Атрибуты, описывающие тип ответа и режим загрузки файлов.

## Когда использовать <a id="section-3"></a>
- **Returns** — когда нужен DTO‑ответ или unwrap вложенных данных.
- **Download** — когда ответом является файл.
- **RawResponse** — когда нужна строка тела без декодирования.
- **ContinuationResult** — когда готовность финала и его тип отличаются от стартового ответа.

## Returns <a id="section-4"></a>
**Target:** class
**Параметры:**
- `response: string` — класс DTO результата
- `unwrap?: string` — путь к данным внутри ответа
- `type?: string` — override DTO класса после unwrap
- `mismatchMessage?: string` — сообщение при несовпадении итогового типа

Тип конечного успешного значения проверяется автоматически, включая результат
обработчика расширения и стадий. Подкласс допустим; иной тип даёт
`hydration_error/response_type_mismatch`. [Сообщения, фабрика исключений и границы](../results/exceptions.md).

`type` нужен, когда у разных запросов один и тот же `unwrap`,
но нужен другой DTO (например, разные модели в одном и том же контейнере).
Если `type` не задан — используется `response`.

Гидратор клиента применяет подключённый `ClientConfig::hydration`, в том числе
к plain-классам. [Внешние правила и диагностика](../../guides/dto/plain-models.md) одинаковы
для синхронного результата и promise.

Пример:
```php
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Returns(UserDto::class, unwrap: 'data.user')]
final class GetUser extends AbstractRequest {}
```

### Проверка декларации <a id="declaration-validation"></a>

До HTTP и hooks запроса SDK проверяет существование всех объявленных классов DTO
(response, необязательный type и фактический getResponseType()) и соответствие
объявленного гидратора DtoHydratorInterface. Ошибочная декларация даёт configuration_error
без отправки запроса. Конструкторы DTO не вызываются и не обязаны быть публичными;
штатные правила полей и гидратация проверяются отдельно.

Проверка декларации предшествует валидации данных запроса, включая validateCustom().
Если неверны и декларация, и входные данные, результат содержит configuration_error
и пустой validationErrors: валидация данных ещё не запускалась. При корректной
декларации неверные данные по-прежнему дают validation_failed. В обоих случаях
HTTP не отправляется; порядок одинаков для send() и sendAsync().

Явный Returns должен быть корректным даже тогда, когда Download или конечный response
handler не использует гидратацию. EarlyReturn не отменяет эту проверку; RawResponse
с Returns остаётся несовместимым. Динамические accessor-ы проверяются для текущего
запроса без кеширования вердикта по его классу. Зависимости обработчика создаются
только при необходимости гидратации: ошибка DI всё ещё возможна после HTTP.
Новая настройка и ранний вызов supports() гидратора не добавляются.

### Строгий unwrap <a id="section-5"></a>

Указанный `unwrap` — обязательный путь в dot-нотации, включая индексы списков
(`data.0`). Путь проверяется после `BeforeHydrate`. Если его нет, результат содержит
`hydration_error` с причиной `unwrap_path_missing`; HTTP-ответ сохраняется.
Корень документа не подставляется вместо отсутствующих данных.

Найденный `null` отличается от отсутствующего пути. `Returns` объявляет обязательный
DTO: null или scalar вместо его данных дают `unexpected_response_shape`. Пустой
массив передаётся обычной гидратации и может быть допустимым для DTO с defaults.
`unwrap: null` означает отсутствие извлечения. Для самой проверки unwrap
дополнительные настройки клиента не нужны.

Если отсутствие объекта нормально, объявите DTO-обёртку с nullable-полем:

```php
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class OrderDto extends AbstractDto
{
    public function __construct(public string $id) {}
}

final readonly class ActiveOrderDto extends AbstractDto
{
    public function __construct(public ?OrderDto $order) {}
}

#[Returns(ActiveOrderDto::class, unwrap: 'data')]
final class GetActiveOrder extends AbstractRequest {}
```

Для `{"data":{"order":null}}` результат успешен и `order === null`.
Прямой nullable DTO результата этим атрибутом не объявляется. Корневой null/204
без DTO сохраняет [свой контракт](../results/handles.md#section-3).
Для DTO без unwrap корневые null/204/пустое тело нормализуются в `[]`.

Задайте путь, соответствующий ответу; для корневого DTO не указывайте unwrap.
Разные формы ответа можно нормализовать через `BeforeHydrate`/ResponseHandler.
У `ContinuationResult::unwrap` и pagination itemsPath отдельные правила;
строгий контракт здесь относится к `Returns`.

## ContinuationResult <a id="section-6"></a>

**Target:** class. `finalType: string` — обязательный класс финального DTO;
`unwrap?: string` — путь финала и критерий его присутствия;
`pollRequest?: string` — класс запроса с одним обязательным scalar token-параметром;
`defaultMode?: ContinuationMode` — режим провайдера;
`stateResolver?: string` — класс `ContinuationStateResolverInterface` без обязательных
аргументов конструктора.

Приоритет критерия: `stateResolver` атрибута → непустой `unwrap` → resolver клиента.
Если критерия нет, ожидание даёт configuration-ошибку до polling. В отличие от
`Returns`, отсутствие/null данных по `unwrap` здесь означает Pending. Ошибка данных Ready
немедленно завершает ожидание и сохраняет полный путь и последний HTTP-ответ.
Полный контракт, пример resolver — в [Provider Async Await](../../guides/recipes/continuation.md).

## RawResponse <a id="section-7"></a>

**Target:** class. Параметров нет. Опционален; настройки ClientConfig не нужны.

```php
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\RawResponse;
use ApiSutra\Core\AbstractRequest;

#[Get('/export')]
#[RawResponse]
final class ExportRequest extends AbstractRequest
{
}

$result = $client->send(new ExportRequest())->raw();
$text = $result->data;
```

Для одного исполнения: `$request->withRawResponse()->send()`. Приоритет:
runtime → атрибут → стандартный Auto. `withRawResponse(false)` выбирает Auto,
`withRawResponse(null)` снимает override и возвращает наследование атрибута.
Цепочка не меняет исходный запрос.

Raw возвращает body, предоставленное транспортом, без JSON и response format handlers:
`'null'` остаётся строкой, пустое тело и 204 дают `''`. Режим полезен при ошибочном
Content-Type или намеренном чтении JSON как строки. В Auto неизвестный явно указанный
не-JSON формат без DTO и так возвращается строкой; подробности —
[контракт ответа](../results/handles.md#section-3).

Raw несовместим с Returns/response DTO, пагинацией и download: итоговое сочетание
отклоняется как `configuration_error` до HTTP. Для файлов используйте Download.
BeforeHydrate для строки пропускается; остальные hooks сохраняются. HTTP-ошибка
не становится успехом: например, 429 остаётся ошибкой независимо от Raw.

Для ручной диагностики исходное тело уже есть в `$result->response?->body`, даже
при ошибке декодирования в result-first режиме. `send()->raw()` получает весь
ExecutionResult, а не включает RawResponse. При `throwOnErrors` исключение может
прервать получение результата. HTTP cache хранит исходный response: Auto и Raw
используют один ключ и не требуют префиксов или другого кеша.

## Download <a id="section-8"></a>
**Target:** class
**Параметры:** нет
**Эффект:** помечает запрос как загрузку файла; результат — FileResponse.

Тело принимается потоком во временный файл без дополнительных настроек.
`withDownloadTo()` задаёт локальный путь или writable поток для окончательного
результата. Владение, кеш и совместимость описаны в [гайде файлов](../../guides/recipes/files.md).

Returns::hydrator выбирает [пользовательский гидратор DTO](../dto/hydrators.md): null наследует клиент, false выбирает штатный путь, имя класса разрешается при выполнении.
