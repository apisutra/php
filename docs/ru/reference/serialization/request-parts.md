<!-- languages --> <a href="../../../en/reference/serialization/request-parts.md">English</a> · <a href="request-parts.md">Русский</a> <!-- /languages -->
# Сборка частей запроса <a id="section-1"></a>

Настройки сериализации и маппинга.

Базовая проверка обязательных полей DTO и совместимости native-типов, строгий JsonCast
и безопасные ошибки гидратации работают автоматически;
[nullable/default определяются объявлением DTO](../dto/defaults.md#section-2).
Для запрета неявных scalar conversions, проверки элементов списков и независимых
от атрибутов моделей используйте [HydrationRules](../../guides/dto/plain-models.md).
Профили дат и пользовательские casts нужны для особенностей формата провайдера.
[Исходный ответ для диагностики](../dto/diagnostics.md#section-4)
доступен и при выключенном debug.

## NamingStrategy <a id="section-2"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\NamingStrategy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    namingStrategy: NamingStrategy::SnakeCase,
);
```

По умолчанию `NamingStrategy::None` (имена не преобразуются).
Меняйте стратегию, если API использует другую схему именования для request/query/header/path.
Входящий naming fallback задаётся отдельно через `DtoHydrationProfile` или
`RulePolicy::naming` во [внешнем наборе](../../guides/dto/plain-models.md).
Для body DTO каноническая naming policy рекомендуется через `DtoSerializationProfile`.

## QueryArrayFormat <a id="section-3"></a>
```php
use ApiSutra\Enums\Http\QueryArrayFormat;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    queryArrayFormat: QueryArrayFormat::Brackets,
);
```

По умолчанию используется `QueryArrayFormat::Brackets`.
Меняйте формат, если провайдер ожидает `comma` или `repeat`.

## Текстовый boolean <a id="section-4"></a>

`textBooleanFormat` необязателен: `BooleanFormat::Numeric` по умолчанию передаёт
true как `1`, false как `0` в query и скалярных полях multipart.
Для API, ожидающего слова, настройте клиент:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Serialization\BooleanFormat;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    textBooleanFormat: BooleanFormat::Literal,
);
```

Точечное переопределение — `#[Cast(BooleanCast::class, BooleanFormat::Numeric)]`
или Literal; см. [BooleanCast](casts.md#section-8).
Сначала применяется cast, затем формат оставшегося boolean. Строки cast не
переинтерпретируются. Настройка не меняет JSON, DTO, path или заголовки.

NamingStrategy определяет, как SDK преобразует имена свойств
в ключи запроса и обратно.

## Доступные режимы <a id="section-5"></a>
- `None` — без преобразований
- `SnakeCase` — `camelCase` → `snake_case`

По умолчанию используется `None`.

## Где применяется <a id="section-6"></a>
- **Запросы**: при сборке query/body, если не задан `name`
- **DTO**: при гидрации, если нет явного пути в `From`/`Map`/`Nested` или `FieldRule::from()`
- **DTO‑сериализация**: если нет `#[To]`; для DX DTO рекомендуемая naming policy задаётся через `DtoSerializationProfile`

## Как переопределять <a id="section-7"></a>
- `#[Query(name: ...)]` / `#[Body(nested: ...)]` — для запросов
- `#[From('data.id')]` — для входящих данных (DTO)
- `FieldRule::from('data.id')` — для входящих данных во внешнем наборе
- `#[To('user_id')]` — для сериализации DTO

## Пример <a id="section-8"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\NamingStrategy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    namingStrategy: NamingStrategy::SnakeCase,
);
```

## Рекомендации <a id="section-9"></a>
- Пример `ClientConfig::namingStrategy` выше настраивает запросы. Для входящих DTO
  задайте naming в `DtoHydrationProfile` или `RulePolicy` внешнего набора;
  [приоритеты правил](../dto/scalars.md#section-3).
- Если API уже использует `snake_case`, включайте `SnakeCase`.
- Для mixed‑API используйте `None` и задавайте `#[From]/#[To]/#[Query]` точечно.
- Для provider SDK DX DTO naming policy рекомендуется централизовать через `DtoSerializationProfile`,
  а request-level fallback — через `ClientConfig`.

Этот раздел объясняет, как SDK собирает `PreparedRequest`: какие поля идут
в path/query/body, как применяется naming strategy и касты.

## Порядок этапов <a id="section-10"></a>
Перед сериализацией SDK выполняет два шага валидации:
1. стандартная валидация request-атрибутов (`Validate`);
2. контрактная валидация oneOf/discriminator (`RequestOneOf`, `RequestDiscriminator`).

Если контракт нарушен, запрос завершается с `ErrorCode::RequestContractViolation`
до подготовки `PreparedRequest` и до HTTP-вызова.

После этого внутри `Serializer` этапы идут так:
1. `RequestPartsCollector` собирает `query/body/files/headers/placeholders`;
2. применяется enrichment (`credentialsConfig` и `requestEnrichers`);
3. `FilePayloadPreparer` финализирует `body/stream/headers`;
4. собирается `PreparedRequest`.

### Content-Type для JSON body <a id="section-11"></a>
Для JSON body (default case и Base64) SDK выставляет `Content-Type: application/json` по умолчанию,
если заголовок ещё не задан.
Multipart и Binary задают свой Content-Type (boundary, mime-type).
Переопределить можно через `#[Header('Content-Type')]` или request enricher.

`RequestOneOf` поддерживает top-level поля и dot-path для вложенных структур.
Режим валидации задаётся через `OneOfMode` (`ExactlyOne` или `AtLeastOne`).

## Request parts enrichment (до finalize payload) <a id="section-12"></a>
Enrichment работает с `RequestPartsBag` (не с raw JSON-строкой), поэтому
подходит для `body/query/multipart form` и не ломает общий пайплайн.

Встроенный `CredentialsEnricher` включается через `ClientConfig::credentialsConfig`.

### Merge-policy <a id="section-13"></a>
Приоритеты:
1. runtime overrides (`withCredentials...`)
2. явные поля request
3. provider defaults (`credentialsConfig`)

Merge-режимы:
- `fill-missing` (по умолчанию) — только заполняет отсутствующие ключи
- `overwrite` — перезаписывает конфликтующие значения
- `fail-on-conflict` — бросает `ConfigurationException` при конфликте

### Scope и исключения <a id="section-14"></a>
- scope берётся из runtime (`withCredentialsScope()`), иначе из `AuthScope`
- `#[SkipCredentialsEnrichment]` выключает enrichment на request-классе
- runtime `withCredentialsEnrichment(true/false)` имеет приоритет

### Ограничения MVP <a id="section-15"></a>
- `BodyRoot` не обогащается (чтобы не ломать root‑payload контракты)
- `form`-defaults применяются только к multipart text fields
- файлы (`FileInput`) enrichment не модифицирует

## Базовые правила (convention) <a id="section-16"></a>
Для каждого свойства запроса:
- `#[Path]` или плейсхолдер `{name}` в endpoint → **path**
- `#[Header]` → **headers**
- `#[File]` → **files**
- `#[BodyRoot]` → **весь body целиком**
- `#[Body]` → **body**
- `#[Query]` → **query**
- без property-атрибута:
  - если есть class-level `RequestDefaults` — используется его `unmapped`
  - иначе:
  - `GET/DELETE` → **query**
  - остальные методы → **body**

Приоритеты:
1. `#[Ignore]`
2. property-атрибуты (`Path`, `Header`, `File`, `Query`, `BodyRoot`, `Body`)
3. class-level `RequestDefaults`
4. convention по HTTP-методу

Ограничение: `BodyRoot` нельзя смешивать с `Body` и с полями, которые попадают в body по умолчанию.
Иначе будет `ConfigurationException`.
