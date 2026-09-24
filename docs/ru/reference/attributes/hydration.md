<!-- languages --> <a href="../../../en/reference/attributes/hydration.md">English</a> · <a href="hydration.md">Русский</a> <!-- /languages -->
# Атрибуты DTO <a id="section-1"></a>

## Сигнатуры и targets <a id="section-2"></a>

Имена классов относятся к `ApiSutra\Attributes\DataTransfer`.
Сигнатуры показывают параметры и defaults конструктора; target указывает допустимое
место атрибута. Поведение и приоритеты описаны в тематических ссылках ниже.

| Атрибут | Target | Конструктор |
| --- | --- | --- |
| About | PROPERTY | `About(string $title, ?string $description = null, string\|int\|float\|bool\|array\|null $example = null, ?array $examples = null, ?string $format = null, ?string $nullableReason = null, ?string $note = null)` |
| Cast | PROPERTY | `Cast(string $class, mixed ...$args)` |
| <a id="datetimefrom"></a> DateTimeFrom | PROPERTY | `DateTimeFrom(?string $format = null, ?string $defaultTimezone = null, ?bool $preserveOffset = null, ?bool $strictMissingTimezone = null, ?bool $strictFormat = null, ?DateTimeInvalidBehavior $invalidBehavior = null)` |
| <a id="datetimeto"></a> DateTimeTo | PROPERTY | `DateTimeTo(?string $format = null, ?string $timezone = null)` |
| DefaultValue | PROPERTY | `DefaultValue(int\|float\|string\|bool\|array\|null $value = null, ?string $provider = null, array $when = [ValueState::Missing])` |
| <a id="dtohydrate"></a> DtoHydrate | CLASS | `DtoHydrate(?NamingStrategy $namingStrategy = null, ?string $dateTimeFormat = null, ?string $dateTimeDefaultTimezone = null, ?bool $dateTimePreserveOffset = null, ?bool $dateTimeStrictMissingTimezone = null, ?bool $dateTimeStrictFormat = null, ?DateTimeInvalidBehavior $dateTimeInvalidBehavior = null, ?EmptyStringBehavior $emptyStringBehavior = null, ?ScalarPolicy $scalars = null)` |
| <a id="dtohydrationprofile"></a> DtoHydrationProfile | CLASS | `DtoHydrationProfile(string $class)` |
| <a id="dtoserializationprofile"></a> DtoSerializationProfile | CLASS | `DtoSerializationProfile(string $class)` |
| <a id="dtoserialize"></a> DtoSerialize | CLASS | `DtoSerialize(?EnumOutput $enumOutput = null, ?bool $strictEnums = null, ?NamingStrategy $namingStrategy = null, ?bool $serializeNulls = null, ?string $dateTimeFormat = null, ?string $dateTimeTimezone = null)` |
| EmptyStringAsNull | PROPERTY | `EmptyStringAsNull(bool $blank = false)` |
| From | PROPERTY | `From(string $name, array $fallback = [])` |
| Label | PROPERTY | `Label(string $name)` |
| Map | PROPERTY | `Map(string $name)` |
| Nested | PROPERTY | `Nested(?string $type = null, ?string $itemCast = null, ?string $from = null, array $fallback = [], ?string $each = null, ?string $discriminator = null, ?array $map = null, NestedDiscriminatorMode $discriminatorMode = NestedDiscriminatorMode::Value, NestedUnknownVariant $unknownVariant = NestedUnknownVariant::KeepRaw)` |
| To | PROPERTY | `To(string $name)` |
| Validate | PROPERTY | `Validate(string $rules, ?string $message = null)` |
| Extras | PROPERTY | `Extras()` |
| RequiredInput | PROPERTY | `RequiredInput()` |
| ForbidExplicitNull | PROPERTY | `ForbidExplicitNull()` |
| ConstructorValue | PROPERTY | `ConstructorValue(bool $allowMissing = false)` |
| Shape | PROPERTY | `Shape(ScalarType\|ShapeSpec $value)` |

[Новые декларации](../dto/declarations.md): остаток входа, присутствие/null,
сравнение с конструктором и рекурсивные формы. Общая политика — [HydrationConfig](../dto/configuration.md).

Атрибуты для маппинга, профилей и валидации DTO. Target каждого объявления указан в таблице.

## Когда использовать <a id="section-3"></a>
- **Map** — когда один и тот же внешний ключ нужен и для hydrate, и для serialize.
- **From** — когда входное поле в ответе API называется иначе или лежит в глубине.
- **To** — когда ключ при отправке должен отличаться от имени свойства DTO.
- **Cast** — когда тип нужно преобразовать (даты, enum, числа).
- **About** — когда нужно описать бизнес-смысл response DTO поля для документации
  и tooling.
- **DateTimeFrom / DateTimeTo** — когда для даты нужен типовой property-level override без low-level `Cast`.
- **EmptyStringAsNull** — когда пустая строка в ответе провайдера должна трактоваться как `null`.
- **Nested** — когда поле содержит вложенный объект или список объектов.
- **DefaultValue** — когда нужен дефолт или проверка найденного значения через provider.
- **Validate/Label** — когда нужно локально валидировать DTO и получить читаемые ошибки.

## Cast <a id="section-4"></a>
**Параметры:**
- `class: class-string<HydrationCastInterface|SerializationCastInterface>`
- `...args` — аргументы конструктора

Пример:
```php
#[Cast(DateTimeCast::class)]
public DateTimeImmutable $createdAt;
```

`#[Cast]` имеет приоритет над built-in auto-cast.
Для обычных scalar cases (`int` / `float` / `bool` / `string`) явный `Cast`
часто не требуется, если провайдер отдаёт безопасно приводимое значение.

## About <a id="section-5"></a>
`#[About]` описывает бизнес-смысл DTO-поля для документации, анализа и export tooling.

Минимальный вариант:
```php
#[About(title: 'ИНН физического лица')]
public ?string $inn = null;
```

Расширенный вариант:
```php
#[About(
    title: 'Данные паспорта',
    description: 'Структурированные паспортные данные, если провайдер вернул их отдельным объектом.',
    example: [
        'series' => '1234',
        'number' => '567890',
        'issued_at' => '2020-01-15',
    ],
    format: 'object',
)]
public ?array $passport = null;
```

Поля:
- `title` — обязательное короткое человекочитаемое имя поля
- `description` — развёрнутое бизнес-описание поля
- `example` — один типовой пример значения; может быть scalar, JSON-строка или array
- `examples` — несколько примеров, каждый подчиняется тем же правилам, что `example`
- `format` — человекочитаемая подсказка о формате, если PHP-типа недостаточно
- `nullableReason` — причина, почему поле может быть `null`
- `note` — дополнительная оговорка или важный нюанс

`About` не дублирует техническую схему поля. Тип, nullable, enum, nested DTO,
collection shape, external field name и casts должны извлекаться из PHP-типа и
атрибутов `From`, `Map`, `Nested`, `Cast` и т.п.

Общее правило заполнения: optional-поля нельзя заполнять догадками. Значения должны
опираться на явный контракт провайдера, документацию, пользовательское описание или
проверенный анализ. Если данных недостаточно, поле остаётся `null`. Это особенно
важно для AI-assisted разметки DTO.

Для `nullableReason` правило строгое: заполняйте его только если причина явно
известна. Если причина неизвестна или есть только предположение, оставляйте `null`.

Для JSON-примеров допустимы оба варианта:
- `array` — структурированный пример; exporter может отрендерить его как JSON
- `string` — буквальный пример значения; он не должен автоматически парситься как JSON

## DateTimeFrom / DateTimeTo <a id="section-6"></a>
Используйте их для типового date-time DX:

```php
#[DateTimeFrom(format: DATE_ATOM, defaultTimezone: 'UTC')]
#[DateTimeTo(format: DATE_ATOM, timezone: 'UTC')]
public DateTimeImmutable $createdAt;
```

- `DateTimeFrom` влияет только на hydration
- `DateTimeTo` влияет на сериализацию свойства DTO, включая DX и wire; формат поля самого Request задаётся requestDateTime
- `#[Cast]` сильнее этих атрибутов и остаётся escape hatch

## EmptyStringAsNull <a id="section-7"></a>
Точечная hydration-нормализация:

```php
#[EmptyStringAsNull]
public ?string $middleName = null;
```

Опция:
- `blank: true` — считать `null` не только `''`, но и строки из пробелов

Подробное поведение и рекомендации: [DTO guide](../../guides/dto/attribute-models.md).

## Map <a id="section-8"></a>
**Параметры:**
- `name: string` — имя внешнего ключа

Пример:
```php
#[Map('user_id')]
public int $userId;
```

`Map` работает в обе стороны:
- hydrate из `user_id`
- serialize в `user_id`

Приоритеты:
- hydrate: `From` -> `Map` -> `NamingStrategy`
- serialize: `To` -> `Map` -> `NamingStrategy`

## From <a id="section-9"></a>
**Параметры:**
- `name: string` — путь/ключ в ответе
- `fallback: array = []` — альтернативные ключи

Пример:
```php
#[From('data.id', fallback: ['id'])]
public int $id;
```

## To <a id="section-10"></a>
**Параметры:**
- `name: string` — имя ключа при сериализации

## Что когда использовать <a id="section-11"></a>
| Сценарий | Рекомендация |
|---|---|
| Один и тот же ключ в обе стороны | `Map` |
| Только hydrate, нужен dot-path/fallback | `From` |
| Только serialize | `To` |
| Разные ключи на вход и выход | `From` + `To` |
| Базовое правило для всего DTO | `NamingStrategy` |

## Nested <a id="section-12"></a>
**Параметры:**
- `type?: string` — конкретный класс одиночного DTO или элемента коллекции
- `itemCast?: string` — cast для каждого элемента массива перед дальнейшей hydration/type-обработкой
- `from?: string` — путь в ответе
- `fallback: array = []`
- `each?: string` — для коллекций
- `discriminator?: string` — путь к discriminator (для `Value`), либо путь к объекту-обертке (для `Key`)
- `map?: ?array` — маппинг discriminator -> class-string DTO
- `discriminatorMode: NestedDiscriminatorMode = Value` — откуда брать discriminator (`Value` | `Key`)
- `unknownVariant: NestedUnknownVariant = KeepRaw` — политика для неизвестных вариантов (`KeepRaw` | `Skip` | `Error`)

`itemCast` нужен, когда nested-массив уже структурно корректный, но каждый элемент надо дополнительно преобразовать.
Типовой пример: `list<data-uri-string> -> list<Base64File>`.
Класс `itemCast` должен реализовывать `HydrationCastInterface` и создаваться без аргументов.

Применение к одному объекту, списку и typed collection, включая порядок Cast/Nested,
описано в [формах DTO](../dto/shapes.md#section-4).

## DefaultValue <a id="section-13"></a>
**Параметры:**
- `value?: int|float|string|bool|array|null`
- `provider?: string` — класс `DefaultValueProviderInterface`, создаваемый без аргументов
- `when: array = [ValueState::Missing]`

Если `provider` не задан — используется `value`.
Провайдер нужен, когда значение зависит от контекста или требует логики.
Ненулевой `value` вместе с `provider` — ошибка конфигурации.

Provider применим к Missing/Null/Present. [Порядок и пример валидации
найденного значения](../dto/defaults.md#section-9) находятся
в контракте defaults.

Автоматическое значение для missing typed collection и его приоритет относительно
DefaultValue описаны в [коллекциях](../dto/collections.md).

## Label <a id="section-14"></a>
**Параметры:**
- `name: string` — человекочитаемое имя поля

## Validate <a id="section-15"></a>
**Параметры:**
- `rules: string` — правила валидации
- `message?: string` — кастомное сообщение

Если `message` не задан — используется дефолтное сообщение валидатора.

Пример:
```php
#[Label('Email')]
#[Validate('required|email', message: 'Некорректный email')]
public string $email;
```

## Сочетание с внешними правилами <a id="section-16"></a>

`FieldRule` конфликтует с From, Map, Nested, Cast, DateTimeFrom, EmptyStringAsNull
и DefaultValue того же свойства. Атрибуты других полей продолжают работать.
Casts/providers через HydrationContext получают текущий набор и при атрибутной регистрации,
включая `Nested(itemCast:)`. API и приоритеты — в
[справке внешних правил](../dto/field-rules.md#section-3).
