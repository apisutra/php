<!-- languages --> <a href="../../../en/reference/request/declaration.md">English</a> · <a href="declaration.md">Русский</a> <!-- /languages -->
# Декларация запроса <a id="section-1"></a>

Краткий гайд по созданию запросов, опциям выполнения и отправке.

## Базовая структура <a id="section-2"></a>
```php
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/users')]
#[Returns(UserDto::class, unwrap: 'data')]
final class GetUser extends AbstractRequest
{
    public function __construct(
        #[Query('id')] public int $id,
    ) {}
}
```

## Источники данных <a id="section-3"></a>
- `#[Query]` — параметры URL
- `#[Body]` — тело запроса
- `#[Path]` — плейсхолдеры в пути
- `#[Header]` — заголовки
- `#[File]` — файлы

Полный перечень: [атрибуты запроса](../attributes/request.md)

## Body DTO для повторяющихся payload <a id="section-4"></a>
Если одно и то же тело используется в нескольких запросах — вынесите его в DTO
и используйте как свойство запроса.

```php
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Http\Put;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class UserPayload extends AbstractDto
{
    public function __construct(
        public string $email,
        public string $name,
    ) {}
}

#[Post('/users')]
final class CreateUser extends AbstractRequest
{
    public function __construct(
        #[Body('user')]
        public UserPayload $payload,
    ) {}
}

#[Put('/users/{id}')]
final class UpdateUser extends AbstractRequest
{
    public function __construct(
        public int $id,
        #[Body('user')]
        public UserPayload $payload,
    ) {}
}
```

DTO сериализуется через `#[To]`, касты и naming strategy. `#[Body]` задаёт
вложенный путь, если он нужен.

## Много полей в body без бойлерплейта <a id="section-5"></a>
Если в request-классе много payload-полей, используйте class-level `RequestDefaults`
и оставляйте property-атрибуты только там, где нужен override:

```php
use ApiSutra\Attributes\Request\RequestDefaults;
use ApiSutra\Enums\Request\RequestUnmappedTarget;

#[RequestDefaults(unmapped: RequestUnmappedTarget::Body)]
final class PatchSomethingRequest extends AbstractRequest {}
```

Это рекомендуемый подход для «шумных» `POST/PUT/PATCH` запросов.
Полный контракт и приоритеты: [Request attributes](../attributes/request.md#section-11).

## Корневой body для JSON Patch / bulk <a id="section-6"></a>
Если endpoint ожидает body в виде `[...]` (а не `{...}`), используйте `BodyRoot`:

```php
use ApiSutra\Attributes\Request\BodyRoot;

#[BodyRoot]
public array $operations;
```

В этом случае SDK отправит `operations` как корневой payload.
Подробности и ограничения: [Request attributes](../attributes/request.md#section-6).

## OneOf и discriminator для polymorphic body <a id="section-7"></a>
Для полиморфных payload используйте class-level `RequestOneOf` и `RequestDiscriminator`.
Так контракт проверяется до транспорта, а диагностика приходит в стандартном формате ошибки.

```php
use ApiSutra\Attributes\Request\RequestDiscriminator;
use ApiSutra\Attributes\Request\RequestOneOf;
use ApiSutra\Enums\Request\OneOfMode;

#[RequestOneOf(
    name: 'signature_payload',
    variants: [
        'cloudcrypt' => ['certificateId'],
        'goskey' => ['goskeyData'],
    ],
    requiredCommon: ['type', 'contents'],
    mode: OneOfMode::ExactlyOne,
)]
#[RequestDiscriminator(
    field: 'type',
    map: [
        'CERT_PROVIDER' => 'cloudcrypt',
        'HSM_PROVIDER' => 'goskey',
    ],
)]
final class CreateSignatureRequest extends AbstractRequest {}
```

Для вложенных контрактов можно использовать dot-path:
```php
#[RequestOneOf(
    name: 'signature_payload',
    variants: [
        'cloudcrypt' => ['signature.certificateId'],
        'goskey' => ['signature.goskey.data'],
    ],
    requiredCommon: ['type', 'signature.contents'],
    mode: OneOfMode::AtLeastOne,
)]
```

Подробности: [Request attributes: RequestOneOf](../attributes/request.md#section-13),
[Request attributes: RequestDiscriminator](../attributes/request.md#section-14).

## Сериализация <a id="section-8"></a>
Правила default‑маппинга и приоритеты описаны отдельно:
[сериализация запроса](../serialization/README.md)

## Поведение запроса <a id="section-9"></a>
Для cache/retry/timeout/rate‑limit используйте behavior‑атрибуты.
Полный перечень: [атрибуты поведения](../attributes/behavior.md)

## Кеширование <a id="section-10"></a>
Для управления кешем используйте:
- `withCache()` / `withoutCache()`
- `withCacheScope(string $scope)` — дополнительная метка кеша для исполнения внутри автоматической identity/tenant
- `clearCache()` — инвалидирование вариантов запроса в его пространстве

Подробнее: [HTTP-кеш](../execution/cache.md)

## Авторизация <a id="section-11"></a>
- `#[AuthScope]` — выбрать scope для запроса.
- `#[NoAuth]` — отключить auth.
- Runtime‑override: `withAuth()`, `withoutAuth()`, `withAuthScope()`, `forceAuthScope()`.

Обычно достаточно `#[AuthScope]` или дефолтного `auth` из `ClientConfig`.
`forceAuthScope()` используйте только если нужно пробить `#[NoAuth]`.

## Provider credentials enrichment <a id="section-12"></a>
Если провайдер требует служебные креды в `body/query/multipart form`,
настраивайте это централизованно через `ClientConfig::credentialsConfig`,
а не дублируйте поля в каждом request-классе.

По умолчанию merge‑режим `fill-missing`:
- явные поля запроса не перезаписываются
- defaults провайдера заполняют только отсутствующие ключи

Для точечных исключений:
- `#[SkipCredentialsEnrichment]` — отключить enrichment для конкретного request
- runtime‑override:
  - `withCredentialsEnrichment()` / `withoutCredentialsEnrichment()`
  - `withCredentialsMergeMode(...)`
  - `withCredentialsScope(...)`

```php
use ApiSutra\Attributes\Request\SkipCredentialsEnrichment;
use ApiSutra\Enums\Request\CredentialsMergeMode;

$result = $request
    ->withCredentialsScope('system')
    ->withCredentialsMergeMode(CredentialsMergeMode::Overwrite)
    ->send();
```

## Пагинация <a id="section-13"></a>
Для запросов с поддержкой пагинации:
```php
$result = $request->paginate()->all();
```
Режимы задаются через `PaginationRule` (single/all/pages/range) и могут быть переопределены:
```php
use ApiSutra\Pagination\PaginationRule;

$result = $request->rules(PaginationRule::pages(2))->send()->raw();
```
Подробнее: [атрибуты поведения](../attributes/behavior.md) и [пагинация](../execution/pagination.md)

## Runtime‑опции (частые) <a id="section-14"></a>
```php
$result = $request
    ->withCache(60)
    ->withTimeout(5)
    ->withHeader('X-Trace-Id', $traceId)
    ->send();
```
Ещё примеры: `withRetry()`, `withoutCache()`, `withRateLimit()`, `withDelay()`, `withTraceId()`.

`withDeadline($deadline)` / `withoutDeadline()` применяют или снимают
[общий внешний срок](../execution/deadlines.md#section-4).
`withRawResponse()` выбирает [строку ответа без декодирования](../attributes/response.md#section-7).
Все эти опции относятся к новому исполнению, не мутируя исходный request.

`withRetry(attempts)` сохраняет остальные параметры повторов и не подтверждает
безопасность POST/PATCH. Она определяется конфигом клиента и необязательным
`#[Retry(safe: true/false)]` и опциональным RetrySafetyPolicyInterface;
неуказанный safe и null равнозначны. Подробнее:
[безопасность повторов](../execution/retry.md#section-6).
Для credentials enrichment см. блок выше.

## Отправка и результат <a id="section-15"></a>
```php
$handle = $request->send();          // Синхронное выполнение.
$promise = $request->sendAsync();    // ResultPromiseInterface<ResultHandle>.
$asyncHandle = $promise->wait();     // Готовый ResultHandle.
$raw = $asyncHandle->raw();          // ExecutionResult.
$data = $asyncHandle->dataOrFail();  // Данные или исключение.
$resolved = $request->resolvedAsync(); // Новое исполнение; промис ResolvedResultInterface.
```

sendAsync возвращает [типизированный промис](../results/promises.md).
Готовый handle после wait предоставляет обычные синхронные методы чтения.

Async-пагинация ждёт каждую зависимую страницу перед следующей, а независимые
исполнения продвигаются во время HTTP и ожиданий SDK. Promise агрегата разрешается
после обхода. Ошибки при throwOnErrors отклоняют публичный Promise;
wait() дожидается выдачи. См. [контракт async](../execution/transport.md).

## Полный URL для отдельного исполнения <a id="section-16"></a>

`withUrl($url)` задаёт готовый абсолютный адрес, `withoutUrl()` очищает override.
Исходный request/config не меняется. Base path/query не дописываются; auth, общие
request enrichers и кеш автоматически не включаются. Для относительных endpoint
остаётся `withBaseUrl()`, со встроенной изоляцией credentials при смене origin.

Полный контракт, пример загрузки: [внешние URL](../serialization/uri-query.md).

## Назначение скачиваемого файла <a id="section-17"></a>

Для `#[Download]` доступны `withDownloadTo(string|StreamInterface $target, bool $overwrite = false)`
и `withoutDownloadTo()`. По умолчанию существующий путь защищён от замены; пользовательский
поток SDK не закрывает. Без цели используется временный файл автоматически.
Контракт сохранения и владения — [файлы](../../guides/recipes/files.md).

`withCache(10)->withoutCache()->withCache()` вновь включает кеш с TTL 10:
null здесь означает «не задавать новый TTL». `withoutRateLimit()` выключает применение
лимитера, даже если объект прежнего override сохранён внутри опций. Свежая execution-копия
наследует исходные request-опции; для полного нового набора применяйте `RequestOptions::empty()`
через явный `RequestExecution`, а не предположение о неявном сбросе всех настроек.

## Диагностика RequestContractViolation <a id="section-18"></a>
Если нарушен class-level контракт запроса (`RequestOneOf`/`RequestDiscriminator`),
SDK возвращает ошибку `ErrorCode::RequestContractViolation` до HTTP-вызова.

Ключи в `RequestError.context`:
- `contract` — имя oneOf-контракта;
- `discriminatorField` — поле discriminator;
- `discriminatorValue` — текущее значение discriminator;
- `matchedVariant` — фактически выбранный/разрешённый вариант;
- `filledVariants` — список фактически заполненных вариантов;
- `violations` — машинно-читаемые причины нарушения контракта.

Типовые `violations.code`:
- `none_selected`, `multiple_selected`
- `required_common_missing`, `unknown_variant_field`
- `unknown_discriminator_field`, `unknown_discriminator_value`
- `discriminator_mismatch`, `prohibited_variant_fields`
- `invalid_dot_path_root` (когда dot-path указывает в scalar-корень)

Для успешных запросов с oneOf в `requestDebug()` дополнительно доступен блок `oneOf`
с краткой диагностикой выбранного варианта.
