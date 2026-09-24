<!-- languages --> <a href="../../../en/reference/attributes/request.md">English</a> · <a href="request.md">Русский</a> <!-- /languages -->
# Объявление и композиция запросов <a id="section-1"></a>

## Сигнатуры и targets <a id="section-2"></a>

Имена классов относятся к `ApiSutra\Attributes\Request`.
Сигнатуры показывают параметры и defaults конструктора; target указывает допустимое
место атрибута. Поведение и приоритеты описаны в тематических ссылках ниже.

| Атрибут | Target | Конструктор |
| --- | --- | --- |
| AuthScope | CLASS | `AuthScope(string\|BackedEnum $scope)` |
| Body | PROPERTY | `Body(?string $nested = null)` |
| BodyRoot | PROPERTY | `BodyRoot()` |
| File | PROPERTY | `File(?string $name = null, FileFormat $format = FileFormat::Multipart)` |
| Header | PROPERTY | `Header(string $name)` |
| Ignore | PROPERTY | `Ignore()` |
| OperationDescriptor | CLASS | `OperationDescriptor(?string $title = null, ?string $description = null, ?string $note = null)` |
| Path | PROPERTY | `Path(?string $name = null)` |
| Query | PROPERTY | `Query(?string $name = null, ?QueryArrayFormat $arrayFormat = null, ?bool $nullable = null)` |
| RequestDefaults | CLASS | `RequestDefaults(RequestUnmappedTarget $unmapped = RequestUnmappedTarget::Convention)` |
| RequestDiscriminator | CLASS | `RequestDiscriminator(string $field, array $map)` |
| RequestOneOf | CLASS / REPEATABLE | `RequestOneOf(string $name, array $variants, array $requiredCommon = [], OneOfMode $mode = OneOfMode::ExactlyOne)` |
| <a id="skipcredentialsenrichment"></a> SkipCredentialsEnrichment | CLASS | `SkipCredentialsEnrichment()` |

Атрибуты, определяющие источники данных запроса (query/body/path/header/file),
class-level request defaults и request-level DX metadata.

## Когда использовать <a id="section-3"></a>
- **Query** — фильтры/поиск, параметры в URL.
- **Body** — основной payload (POST/PUT/PATCH).
- **BodyRoot** — корневой payload (например `[...]` для JSON Patch).
- **Path** — подстановка в URL (`/users/{id}`).
- **Header** — служебные значения (идемпотентность, trace‑id).
- **File** — загрузка файлов.
- **Ignore** — исключить поле из сериализации.
- **RequestDefaults** — задать class-level default для неразмеченных свойств.
- **RequestOneOf** — декларативно описать oneOf-контракт payload.
- **RequestDiscriminator** — связать значение discriminator с вариантом oneOf.
- **OperationDescriptor** — дать request-классу человекочитаемый DX-descriptor (`title`, `description`, `note`).
- **AuthScope** — выбор auth‑scope для запроса.

## Query <a id="section-4"></a>
**Target:** property
**Параметры:**
- `name?: string` — имя параметра (по умолчанию имя свойства)
- `arrayFormat?: QueryArrayFormat` — формат массивов
- `nullable?: ?bool` — включать `null` в query; null наследует конфиг

Если `arrayFormat` не задан, используется `ClientConfig::queryArrayFormat`.
`nullable` по умолчанию null — наследуется `ClientConfig::serializeNulls` (false).
Явное true включает `key=`, false исключает null независимо от конфига.
Правила boolean, пустых значений и списков описаны в [сериализации](../serialization/uri-query.md#section-6).

Пример:
```php
use ApiSutra\Attributes\Request\Query;

#[Query('q')]
public string $query;
```

## Body <a id="section-5"></a>
**Target:** property
**Параметры:** `nested?: string` — путь вложения в body

Если `nested` не задан — значение пишется в корень body.

Пример:
```php
use ApiSutra\Attributes\Request\Body;

#[Body('payload')]
public array $payload;
```

## BodyRoot <a id="section-6"></a>
**Target:** property
**Параметры:** нет
**Назначение:** сделать значение свойства **всем** body запроса.

Подходит для endpoint'ов, где контракт требует корневой массив/скаляр (например JSON Patch):
```php
use ApiSutra\Attributes\Request\BodyRoot;

#[BodyRoot]
public array $operations;
```

Ограничения:
- только один `BodyRoot` в request-классе;
- нельзя смешивать с `Body`/body-полями по умолчанию;
- нельзя смешивать с `File` в одном request.

`Path`/`Query`/`Header` можно использовать вместе с `BodyRoot`.

## Path <a id="section-7"></a>
**Target:** property
**Параметры:** `name?: string` — имя плейсхолдера в `path`

Если `name` не задан — используется имя свойства.
Если имя свойства уже совпадает с плейсхолдером в endpoint (`{id}` -> `$id`),
атрибут `#[Path]` можно не указывать.
Используйте `#[Path('...')]`, когда имя свойства и плейсхолдера различаются.

Пример:
```php
use ApiSutra\Attributes\Request\Path;

#[Path('id')]
public int $userId;
```

## Header <a id="section-8"></a>
**Target:** property
**Параметры:** `name: string` — имя заголовка

Пример:
```php
use ApiSutra\Attributes\Request\Header;

#[Header('X-Request-Id')]
public string $requestId;
```

## File <a id="section-9"></a>
**Target:** property
**Параметры:**
- `name?: string` — имя поля
- `format: FileFormat = Multipart` — формат файла

По умолчанию формат — `Multipart`.

Пример:
```php
use ApiSutra\Attributes\Request\File;
use ApiSutra\Enums\Http\FileFormat;

#[File('document', FileFormat::Multipart)]
public FileInput $document;
```

## Ignore <a id="section-10"></a>
**Target:** property
**Параметры:** нет
**Эффект:** исключает поле из сериализации

## RequestDefaults <a id="section-11"></a>
**Target:** class
**Параметры:** `unmapped: RequestUnmappedTarget = Convention`

Назначение: задать default target для **неразмеченных** свойств запроса.

Режимы:
- `Convention` — поведение по HTTP-методу (`GET/DELETE` -> query, остальные -> body)
- `Query` — неразмеченные свойства идут в query
- `Body` — неразмеченные свойства идут в body

Пример:
```php
use ApiSutra\Attributes\Request\RequestDefaults;
use ApiSutra\Enums\Request\RequestUnmappedTarget;

#[RequestDefaults(unmapped: RequestUnmappedTarget::Body)]
final class PatchSomethingRequest extends AbstractRequest {}
```

Приоритеты:
1. `#[Ignore]`
2. property-атрибуты (`Path`, `Header`, `File`, `Query`, `Body`, `BodyRoot`)
3. `RequestDefaults`
4. convention по HTTP-методу

## OperationDescriptor <a id="section-12"></a>
**Target:** class
**Параметры:**
- `title?: string`
- `description?: string`
- `note?: string`

Назначение: добавить к request-классу краткий DX-descriptor без смешивания
с runtime result meta и без отдельного каталога.

Пример:
```php
use ApiSutra\Attributes\Request\OperationDescriptor;

#[OperationDescriptor(
    title: 'Get history',
    description: 'Returns rights history for the object.',
    note: 'Available only for paid plans.',
)]
final class GetHistoryRequest extends AbstractRequest {}
```

Доступ из request:
- `getOperationDescriptorAttribute()`
- `getOperationTitle()`
- `getOperationDescription()`
- `getOperationNote()`

## RequestOneOf <a id="section-13"></a>
**Target:** class (repeatable)
**Параметры:**
- `name: string` — имя контракта (должно быть уникальным в классе)
- `variants: array<string, list<string>>` — варианты и их поля
- `requiredCommon: list<string>` — общие обязательные поля
- `mode: OneOfMode` — режим валидации (`ExactlyOne` или `AtLeastOne`)

Атрибут валидируется **до сериализации и до HTTP**.

Пример:
```php
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
final class CreateSignatureRequest extends AbstractRequest {}
```

Поддерживаются top-level поля (`type`) и dot-path (`signature.certificateId`).
В первой версии dot-path не поддерживает wildcard/индексы (`*`, `0` и т.п.).

## RequestDiscriminator <a id="section-14"></a>
**Target:** class
**Параметры:**
- `field: string` — поле discriminator в request
- `map: array<string, string>` — `значение discriminator -> имя варианта oneOf`

Используется вместе с `RequestOneOf` для правил `requiredIf/prohibitedIf`
без ручных `assert...()` в request-классе.

Пример:
```php
use ApiSutra\Attributes\Request\RequestDiscriminator;

#[RequestDiscriminator(
    field: 'type',
    map: [
        'CERT_PROVIDER' => 'cloudcrypt',
        'HSM_PROVIDER' => 'goskey',
    ],
)]
final class CreateSignatureRequest extends AbstractRequest {}
```

### Семантика заполненности в oneOf <a id="section-15"></a>
- `null` — поле считается незаполненным;
- `''`, `[]`, `0`, `false` — поле считается заполненным.

Это поведение сделано намеренно, чтобы не терять явно переданные значения.

## AuthScope <a id="section-16"></a>
**Target:** class
**Параметры:** `scope: string|BackedEnum` — ключ scope

Пример:
```php
use ApiSutra\Attributes\Request\AuthScope;
use ApiSutra\Core\AbstractRequest;
use App\Provider\Auth\ProviderScope;

#[AuthScope(ProviderScope::System)] // рекомендуемый вариант для enum
final class SomeRequest extends AbstractRequest {}

#[AuthScope('system')] // Явный scope
final class ScopedRequest extends AbstractRequest {}
```
