<!-- languages --> <a href="../../../en/reference/serialization/dto-output.md">English</a> · <a href="dto-output.md">Русский</a> <!-- /languages -->
# Представление DTO: DX и wire <a id="section-1"></a>

## DtoSerializationProfile <a id="section-2"></a>

**Настоятельная рекомендация:** централизуйте SDK DX serialization semantics через
`DtoSerializationProfile`, а wire body semantics — через отдельную transport policy в `ClientConfig`.

Рекомендуемый default для provider SDK:
- `enumOutput: EnumOutput::TitleValueString`
- `strictEnums: false`
- `serializeNulls: false`
- `namingStrategy`: по body DTO контракту провайдера, часто `SnakeCase`

Это даёт:
- канонический `toArray()`
- централизованную настройку через `BaseDto` / `BaseResponseDto`
- мягкий fallback, даже если у enum ещё нет `title()`

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\Serialization\EnumOutput;

final readonly class ProviderDtoSerializationProfile implements DtoSerializationProfileInterface
{
    public function policy(): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy(
            enumOutput: EnumOutput::TitleValueString,
            strictEnums: false,
            namingStrategy: NamingStrategy::SnakeCase,
            serializeNulls: false,
        );
    }

    public function casts(): array
    {
        return [];
    }
}

$dtoProfile = new ProviderDtoSerializationProfile();

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    dtoSerializationProfile: $dtoProfile,
    wireBodySerializationPolicy: new DtoSerializationPolicy(
        enumOutput: EnumOutput::Value,
        strictEnums: false,
        namingStrategy: NamingStrategy::SnakeCase,
        serializeNulls: false,
    ),
    requestPartsEnumOutput: EnumOutput::Value,
);
```

- `dtoSerializationProfile` задаёт DX / `toArray()` semantics
- `wireBodySerializationPolicy` задаёт outbound body semantics
- request-level enum policy задаётся отдельно
- один и тот же профиль рекомендуется привязывать к `BaseDto` / `BaseResponseDto`

## Каноническая сериализация DTO <a id="section-3"></a>
`toArray()` — каноническая DX-сериализация DTO.

Это означает:
- `toArray()` описывает SDK-friendly DTO output, а не обязательно реальный wire payload
- DX DTO semantics централизуются через `DtoSerializationProfile`
- outbound body по умолчанию подчиняется transport-level wire policy
- request/query/header/path semantics живут отдельно в `ClientConfig`
- safe wire default задаётся через `ClientConfig::wireBodySerializationPolicy`

Рекомендуемый путь для provider SDK:
- создать `DtoSerializationProfile`
- привязать его к `BaseDto` / `BaseResponseDto`
- при необходимости явно выровнять wire и DX через `ClientConfig::wireBodySerializationPolicy`

### Важное уточнение <a id="section-4"></a>
Binding на `BaseDto` — это **рекомендуемый carrier**, но не обязательный единственный вариант.

В атрибутной модели автоматика резолвит DTO contract по **иерархии конкретного DTO-класса**:
- через `DtoHydrationProfile` / `DtoSerializationProfile`
- через class-level override `DtoHydrate` / `DtoSerialize`
- через property-level override
- и, если ничего не задано, через zero-config defaults

Это означает:
- в рамках одного SDK может быть не один `BaseDto`, а несколько веток DTO с разными правилами
- разные DTO-иерархии внутри одного клиента могут иметь разные profiles
- источник истины для этой модели — DTO и его профиль

Альтернатива для входящих данных — [внешний набор правил](../../guides/dto/plain-models.md),
переданный клиенту или `Hydrator::forRules()`. Он позволяет оставить классы без
атрибутов и базовых классов ApiSutra. Не совмещайте `DtoRules` и профиль гидратации
на одном классе; исходящий профиль сериализации настраивается отдельно.

Поэтому:
- если у SDK есть один общий `BaseDto`, binding на нём обычно самый удобный
- если у SDK несколько независимых DTO-веток, profiles можно развешивать по соответствующим base classes или конкретным DTO

## DTO‑сериализация для body <a id="section-5"></a>
Если значение свойства — DTO, SDK сериализует его:
- только **публичные** свойства
- `#[To]` для переименования
- dot‑paths в `#[To]` поддерживаются
- `Cast` и registry‑касты применяются до сериализации
- для union‑типов (`A|B`) ветка выбирается по runtime‑значению, а не по порядку в type-hint

## DateTime‑сериализация <a id="section-6"></a>
DateTime semantics после унификации разделены по слоям:

- **DTO hydration** — через `DtoHydrationProfile` / `DtoHydrate` / `DateTimeFrom`
- **DTO DX serialization** — через `DtoSerializationProfile` / `DtoSerialize` / `DateTimeTo`
- **request-level serialization** (`query/header/path` и request body fields) — через `ClientConfig::requestDateTime`
- **wire body serialization** — через `ClientConfig::wireBodySerializationPolicy`

### Parse (гидрация) <a id="section-7"></a>

Входной формат, timezone, offset и реакция на неверную дату описаны в
[контракте гидратации](../dto/profiles.md#section-6).

### Serialize <a id="section-8"></a>
Для DTO DX serialization правила берутся из `DateTimeSerializationPolicy`:
- `format` — формат выходной строки
- `timezone` — если задана, дата приводится к этой зоне перед форматированием
- дефолтный path форматирует только `DateTimeInterface`
- строка не перепарсивается автоматически как дата при DTO serialization

Для request-level сериализации используется `ClientConfig::requestDateTime`.
Для outbound body по умолчанию используется transport-level wire policy.

### Приоритеты <a id="section-9"></a>

Пользовательский Cast и cast по типу выбираются по [общему контракту casts](casts.md#section-3).
Когда используется встроенное форматирование даты, DateTimeTo поля переопределяет
DateTimeSerializationPolicy. В DX базу задаёт профиль DTO, в wire — политика тела.
Кастомный cast может сам определять формат и не обязан читать эту политику.

### Union типы и выбор ветки <a id="section-10"></a>
Для union‑полей SDK сначала пытается выбрать тип, совпадающий с runtime‑значением.
Это убирает зависимость от порядка типов в объявлении:

- `DateTimeInterface|string` со строковым значением обрабатывается как строка
- `string|DateTimeInterface` с объектом даты обрабатывается как `DateTimeInterface`

Если ни одна ветка union не совпала по runtime, используется первый non-null тип.
Сериализация не выполняет неявный повторный разбор строк как дат.

## Enum-сериализация <a id="section-11"></a>

При zero-config DX/wire separation сериализация рассматривается как разные слои:
- **DX / `toArray()`** → через `DtoSerializationProfile`
- **wire body** → через `ClientConfig::wireBodySerializationPolicy`
- **query/header/path** → через request-level client config

Это важно по двум причинам:
- `toArray()` может быть удобнее для разработчика SDK, чем wire payload
- default wire режим не должен ломать provider contract

### Рекомендуемый default для DX DTO <a id="section-12"></a>
Для provider SDK как DX default рекомендуется:
- `enumOutput: EnumOutput::TitleValueString`
- `strictEnums: false`

Это даёт формат `title|value`, а при отсутствии `title()` использует безопасный fallback.

### Рекомендуемый default для wire body <a id="section-13"></a>
Для transport wire default рекомендуется:
- `enumOutput: EnumOutput::Value`
- `strictEnums: false`

### Опции и режимы <a id="section-14"></a>
Доступные режимы `EnumOutput`:
- `Value` — `BackedEnum` → `value`, обычный enum → `name`
- `Name` — всегда `name`
- `Object` — `{value, title}` (только для **body**)
- `TitleValueString` — строка `title|value` (подходит для query/header/path)

### title() и strictEnums <a id="section-15"></a>
Если выбран `Object` или `TitleValueString`, SDK ищет метод `title()` у enum:
- `strictEnums=true` → отсутствие `title()` или неверный тип возвращаемого значения
  приводит к `ConfigurationException`
- `strictEnums=false` → используется fallback:
  - `Object`: `title` = `value`
  - `TitleValueString`: `value|value`

`title()` должен возвращать человекочитаемое название **текущего** значения enum.
Допустимые типы: `string` или `Stringable`.

### Приоритеты и совместимость <a id="section-16"></a>
Приоритет сериализации для enum:
1) `#[Cast]` на свойстве
2) registry‑каст по типу свойства
3) enum‑сериализация по effective policy текущего слоя

Где effective policy берётся:
- DX / `toArray()` → `DtoSerializationProfile`
- wire body → `ClientConfig::wireBodySerializationPolicy`
- query/header/path → request-level config

Для query/header/path допускаются **только скаляры**. Режим `Object` там не используется.

### Мини‑пример <a id="section-17"></a>
```php
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function title(): string
    {
        return match ($this) {
            self::Active => 'Активен',
            self::Inactive => 'Неактивен',
        };
    }
}
```

### Где настроить <a id="section-18"></a>
См. [ClientConfig: Serialization](request-parts.md) — там показано разделение
body DTO profile и request-level enum policy.
