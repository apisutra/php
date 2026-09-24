<!-- languages --> <a href="../../../en/reference/serialization/casts.md">English</a> · <a href="casts.md">Русский</a> <!-- /languages -->
# Casts: гидратация и сериализация <a id="section-1"></a>

## Casts <a id="section-2"></a>

Объектные аргументы `#[Cast]` изолированы между операциями независимо от environment;
см. [правила вычисления атрибутов](../dto/lifecycle.md#section-2).
Готовые экземпляры, явно переданные через `casts`, сохраняют идентичность.

`casts` — правила по PHP-типу для сериализации свойств запроса:

```php
use ApiSutra\Casts\DateTimeCast;
use ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    casts: [
        DateTimeImmutable::class => DateTimeCast::class,
    ],
);
```

Ключ — объявленный PHP-тип, например `DateTimeImmutable::class` или `'string'`.
Относительные `self`/`parent` предварительно разрешаются в полный класс
[в области объявления свойства](../dto/models.md#section-4).
Гидратация ответа через `Returns` не использует эту настройку: ей нужны
`DtoHydrationProfile`, `#[Cast]` свойства или casts внешнего набора. Источники и приоритеты приведены
в [справке casts](casts.md#section-9).

Касты преобразуют значения при сериализации запросов и гидратации DTO.
Для этих операций используются разные источники регистрации.

## Приоритет применения <a id="section-3"></a>

Без внешнего набора для гидратации ненулевого свойства без `#[Nested]`:

1. `#[Cast]` на свойстве.
2. Cast по типу из `DtoHydrationProfile::casts()`.
3. Безопасное приведение scalar и встроенные правила DateTime/enum/DTO.

Тип для union выбирается с учётом входного значения. Если задан `#[Nested]`,
гидратор использует его обработку вместо `#[Cast]` всего свойства; поэлементный
cast задаётся через [Nested.itemCast](../attributes/hydration.md#section-12).
Provider `DefaultValue` выполняется раньше этой обработки; оставшийся null
не передаётся в cast и проверяется на допустимость типом поля.

Для сериализации свойств запроса `#[Cast]` имеет приоритет над клиентским
registry и встроенными преобразованиями. Вложенные DTO сериализуются по своим
[правилам DTO и wire body](dto-output.md#section-2).

## Встроенные касты <a id="section-4"></a>
- `BooleanCast`
- `IntegerCast`
- `FloatCast`
- `DateTimeCast`
- `EnumCast`
- `JsonCast`

### Диапазон IntegerCast и вложенный JSON <a id="section-5"></a>

`IntegerCast` проверяет переполнение до преобразования. При hydrate число вне
диапазона PHP int даёт `HydrationException`; при serialize — `SerializationException`
(в запросе `serialization_error` до HTTP). Null остаётся null, обычные преобразования
сохраняются. Целочисленные строки сравниваются без промежуточного float, включая
ведущие нули. Float и дробные/экспоненциальные строки проверяются в представлении
float; около границы диапазона округление может привести к отказу. Для точного
целого передавайте целочисленную строку или int, для большого ID — сохраняйте string.

### JsonCast <a id="section-6"></a>

`JsonCast::hydrate()` строго разбирает строку как JSON: malformed JSON, пустая строка,
whitespace, invalid UTF-8 и превышение глубины 512 дают `HydrationException` с reason
`invalid_json` и исходным `JsonException` в цепочке `previous`. В DTO добавляется путь
поля, в запросе получается `hydration_error`. Nullable-поле также не скрывает
повреждённую JSON-строку за null. Новая настройка клиента не требуется.

JSON `null`, `false`, `0`, строка, массив и объект сохраняют результаты JSON-декодирования;
объект декодируется в ассоциативный массив. PHP null и нестроковые значения проходят
без JSON-декодирования. Результат затем проверяется по типу поля: например, JSON
`false` корректен, но для `array`-поля даст `invalid_field_type`.
Большие целочисленные JSON-литералы сохраняются строками. Serialize остаётся строгим.
Общая политика чисел — в [сериализации](../dto/scalars.md#section-4).

Повреждённый вложенный JSON обрабатывается как ошибка; для явного fallback объявите
собственный cast с соответствующей политикой.

### Даты и enum <a id="section-7"></a>

Непринятая дата в режиме `DateTimeInvalidBehavior::Throw` даёт `HydrationException`
с reason `invalid_datetime`; expected содержит объявленный формат. Исходная строка
не включается в message. Режим `Null` сохраняет null; если поле его не допускает,
дальнейшая проверка даст `null_not_allowed`. Неверная timezone остаётся ошибкой
конфигурации. То же разделение применяется к явному `DateTimeCast`.

`EnumCast` сохраняет null для неизвестного backed enum value. Неподходящий тип входа
даёт `invalid_field_type`; использование non-backed enum для hydrate считается
ошибкой конфигурации. [Общие правила полей DTO](../dto/defaults.md#section-2).

## Boolean в текстовых полях <a id="section-8"></a>

`BooleanCast` без аргументов сохраняет преобразование в PHP bool, включая
гидрацию. Необязательный формат меняет только его исходящую сериализацию:

```php
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Casts\BooleanCast;
use ApiSutra\Enums\Serialization\BooleanFormat;

#[Query]
#[Cast(BooleanCast::class, BooleanFormat::Literal)]
public bool $enabled = false;
```

Получится `enabled=false` независимо от клиентского `textBooleanFormat`.
Numeric даёт строки `1`/`0`. С явным форматом serialize принимает bool или null;
другие значения вызывают `SerializationException`. Гидрация использует правила входного преобразования.
Явный текстовый cast на JSON/DTO-поле также возвращает строку: применяйте его только
там, где этого требует API. Для общего правила query/multipart достаточно
[настройки клиента](request-parts.md#section-4).

## Регистрация кастов <a id="section-9"></a>

`ClientConfig.casts` настраивает сериализацию свойств **исходящего запроса**:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Casts\DateTimeCast;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    casts: [
        DateTimeImmutable::class => DateTimeCast::class,
    ],
);
```

Эта настройка не подключает cast к гидратации ответа через `Returns`.
Для входящих DTO используйте профиль, `#[Cast]` свойства или
[внешние правила](../dto/scalars.md#section-3).

| Источник | Участие в гидратации DTO | Назначение |
| --- | --- | --- |
| `new Hydrator(casts: $registry)` | Нет | Аргумент сохранён для совместимости; содержимое не применяется к DTO |
| `ClientConfig.casts` | Нет | Casts сериализации свойств запроса |
| `CastRegistry::global()` | Нет, включая `Dto::from()` / `Hydrator::default()` | Общий registry для явного использования вызывающим кодом |
| `ExtensionContext::registerCast()` / `ExtensionRegistry::registerCast()` | Нет | Регистрация в registry расширения; у стандартного SDK-клиента это registry запросов |
| `DtoHydrationProfile::casts()` | Да | Правила по типу для DTO с привязанным профилем |
| `#[Cast]` свойства | Да, если свойство не обрабатывается `Nested` | Явное преобразование значения |
| `RulePolicy::casts` класса или набора | Да | Правила по PHP-типу в `HydrationRules` |
| `FieldRule::cast()` / `ValueShape::list(itemCast:)` | Да | Преобразование поля / элемента с `HandlerSpec` |

Клиентский и глобальный registry не заменяют профиль DTO. Профили сериализации
DTO также настраиваются отдельно от профилей гидратации.
См. [руководство DTO](../../guides/dto/attribute-models.md) и
[параметр ClientConfig.casts](casts.md#section-2).

## Атрибут #[Cast] <a id="section-10"></a>
```php
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Casts\DateTimeCast;

#[Cast(DateTimeCast::class, format: DATE_ATOM)]
public DateTimeImmutable $createdAt;
```

## Примеры поведения <a id="section-11"></a>
- **Safe scalar auto-cast**: при `ScalarPolicy::Legacy` (по умолчанию) `"12"` может стать `12` для `int`, `"12.5"` → `12.5` для `float`, `"true"` → `true` для `bool`. При `Strict` эти строки отклоняются; допустимые типы перечислены в [таблице strict](../dto/scalars.md#section-3).
- **Enum**: для DX / `toArray()` формат задаётся через `DtoSerializationProfile`; для wire body — через `wireBodySerializationPolicy`; для query/header/path — через request-level config клиента.
- **DateTime**: типовой DX идёт через `DtoHydrationProfile` / `DtoHydrate` / `DateTimeFrom` и `DtoSerializationProfile` / `DtoSerialize` / `DateTimeTo`. `#[Cast(DateTimeCast::class, ...)]` остаётся low-level escape hatch. Дефолтный `DateTimeCast::serialize()` принимает только `DateTimeInterface`.
- **Json**: `JsonCast` сериализует массив/объект в JSON‑строку.

## Где применяется <a id="section-12"></a>
- **Request serialization** — свойства запроса
- **DTO hydration** — свойства DTO

## Casts внешнего набора и scope <a id="section-13"></a>

`HandlerSpec` в [HydrationRules](../dto/scope.md#section-2) создаёт
обработчик на каждое применение. HydrationCastInterface и DefaultValueProviderInterface
получают HydrationContext при любой регистрации: атрибуты, Nested.itemCast, профиль
или внешний набор. Выходной SerializationCastInterface получает SerializationContext;
CastInterface объединяет оба направления. Все методы требуют контекст, включая standalone.
Выбор обработчика без нужного направления даёт ConfigurationException без fallback.
Сигнатуры и время жизни — в [контракте контекстов](../dto/scope.md).

Для исходящих значений с receiver cast всего объекта/контейнера запрещён до его вызова:
см. [границы исходящего представления](receiver-output.md#section-1).
Глобальный, клиентский и extension registry не становятся источниками входных casts.
