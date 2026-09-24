<!-- languages --> <a href="../../../en/reference/dto/defaults.md">English</a> · <a href="defaults.md">Русский</a> <!-- /languages -->
# Присутствие, null и значения по умолчанию <a id="section-1"></a>

`#[RequiredInput]` проверяет исходное присутствие до default/provider; `#[ForbidExplicitNull]` запрещает найденный null, сохраняя возможность отсутствия поля. Оба сочетаются с From/Map и DefaultValue; первичный null не включает fallback.

## Обязательные поля и ошибки гидратации <a id="section-2"></a>

Без внешних правил обязательность следует из PHP-типа и объявления DTO.
Приведённые ниже проверки применяются после `From`/fallback,
нормализации пустой строки, `DefaultValue` и автодефолта typed collections.
Внешний `FieldRule::required()` дополнительно требует наличия ключа **до** defaults,
а `forbidExplicitNull()` запрещает исходный null даже для nullable-типа;
см. [формы и присутствие](shapes.md#section-3).

| Объявление и вход | Результат |
| --- | --- |
| Параметр конструктора с default, поле отсутствует | Значение constructor default. |
| Параметр конструктора без default, поле отсутствует, в том числе `?T` | `required_field_missing`. Nullable разрешает null, но не делает аргумент необязательным. |
| Nullable public property вне constructor chain, без default, поле отсутствует | Null. |
| Non-nullable public property вне constructor chain, без default, поле отсутствует | `required_field_missing`. |
| Найден explicit null | Принимается nullable/mixed; constructor default и fallback его не заменяют. Применимый `DefaultValue` для Null может задать замену. |
| После преобразования null, а тип не допускает null | `null_not_allowed`. |
| После допустимых casts значение не подходит типу | `invalid_field_type`; scalar вместо вложенного DTO — `unexpected_response_shape`. |

Конструктор вызывается один раз; проверяется тип его параметра, даже если он
преобразует значение для свойства другого типа. В режиме Legacy сохраняются scalar
conversions и выбор union-веток. Внешний набор может включить
[Strict](scalars.md#section-3), в том числе для результатов casts.

Прямой `DTO::from()` выдаёт `HydrationException` с `reason`, `path`, `expected`,
`actual`. В запросе это `hydration_error` с сохранённым HTTP-ответом. Путь содержит
имена свойств DTO и порядковые индексы (`items[1].id`), при наличии unwrap — его
префикс. Это не обязательно буквальный путь `From` внутри ответа.

Неверные объявления классов, casts, карт `Nested`, timezone и конфликты readonly
инициализации остаются ошибками конфигурации. Произвольная ошибка пользовательского
конструктора или computed не классифицируется как ошибка конкретного поля.

Строгий вложенный JSON описан в [JsonCast](../serialization/casts.md#section-6),
доступ к исходному ответу — в [диагностике](diagnostics.md#section-4).

## Cast, DateTimeFrom/To и DefaultValue <a id="section-3"></a>
Когда нужно преобразовать тип или задать дефолт при отсутствии/NULL.
```php
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Casts\DateTimeCast;

#[DateTimeFrom(format: DATE_ATOM, defaultTimezone: 'UTC')]
#[DateTimeTo(format: DATE_ATOM, timezone: 'UTC')]
public DateTimeImmutable $createdAt;

#[Cast(DateTimeCast::class, format: 'Y-m-d')]
public DateTimeImmutable $legacyCreatedAt;

#[DefaultValue('unknown')]
public string $status;
```

## Empty string normalization <a id="section-4"></a>
По умолчанию `apisutra` не считает `''` эквивалентом `null`:

- `Missing` — ключ отсутствует
- `Null` — ключ есть, значение `null`
- `Present` — значение найдено, включая `''`

Если провайдер использует пустую строку как "значения нет", можно включить это поведение явно.

### Точечно на свойстве <a id="section-5"></a>
```php
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;

#[EmptyStringAsNull]
public ?string $middleName = null;
```

Если нужен режим и для blank strings:

```php
#[EmptyStringAsNull(blank: true)]
public ?string $comment = null;
```

### Централизованно через hydration profile <a id="section-6"></a>
```php
use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;
use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;

final readonly class ProviderDtoHydrationProfile implements DtoHydrationProfileInterface
{
    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy(
            emptyStringBehavior: EmptyStringBehavior::NullIfEmpty,
        );
    }

    public function casts(): array
    {
        return [];
    }
}
```

### Приоритеты <a id="section-7"></a>

Для атрибутной модели без внешнего набора:

1. `#[Cast(...)]`
2. `#[EmptyStringAsNull(...)]`
3. hydration profile-level `emptyStringBehavior`
4. дефолтное поведение `Keep`

Во внешнем наборе нормализация задаётся через `RulePolicy::emptyString`;
[приоритеты и порядок обработки](shapes.md#section-3).

### Практические правила <a id="section-8"></a>
- default поведение ядра не меняется: `''` остаётся `''`
- `EmptyStringAsNull` полезен в основном для `?string`
- если `'' -> null` включено для non-nullable поля и после `DefaultValue` значение всё ещё `null`, hydrator бросает `HydrationException` с reason `null_not_allowed`
- `DefaultValue(... when: [Null])` совместим с этой нормализацией: после `'' -> null` будет работать как для обычного `null`

`DefaultValue` может задавать значение по условию `ValueState`:
- `Missing` — ключ отсутствует
- `Null` — ключ есть, но значение null
- `Present` — значение найдено

Также можно использовать провайдера, если нужен контекст
(например, request/meta/traceId) или более сложная логика:
```php
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Serialization\Context\HydrationContext;

final class StatusDefault implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed
    {
        return 'unknown';
    }
}
```

Если достаточно простого значения — используйте `#[DefaultValue]`.

Provider с `when: [ValueState::Present]` может проверить найденное значение
и вернуть его перед `Nested`. Чтобы одновременно запретить null и проверить
форму списка, используйте один provider с `when: [ValueState::Null, ValueState::Present]`.
Рабочий пример и порядок нормализации — в
[справочнике DefaultValue](../attributes/hydration.md#section-13).

Для typed collections `#[DefaultValue(value: [], when: [ValueState::Missing])]`
обычно не требуется: `missing -> empty collection` покрывается ядром.
Явный `DefaultValue` оставляйте, если нужно:
- обработать `null`
- переопределить fallback
- зафиксировать поведение явно в контракте DTO

## Provider для найденного значения <a id="section-9"></a>

Provider может вычислить замену, вернуть проверенное значение или отклонить его.
`when: [ValueState::Present]` запускает его для найденного ненулевого значения
перед `Nested` и casts. `DefaultValue` не повторяемый атрибут: для запрета null
и проверки формы используйте один provider с `when: [ValueState::Null, ValueState::Present]`.

```php
<?php

declare(strict_types=1);

use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Context\HydrationContext;

final readonly class ListShapeProvider implements DefaultValueProviderInterface
{
    /** @param array<string, mixed> $source */
    public function resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw HydrationException::invalidValue('invalid_list_shape', 'list', get_debug_type($value));
        }

        return $value;
    }
}

final readonly class KnownRecordDto
{
    public function __construct(public string $city)
    {
    }
}

final readonly class RecordsDto
{
    /** @param list<mixed>|null $items */
    public function __construct(
        #[DefaultValue(provider: ListShapeProvider::class, when: [ValueState::Null, ValueState::Present])]
        #[Nested(discriminator: 'kind', map: ['known' => KnownRecordDto::class])]
        public ?array $items = null,
    ) {
    }
}

$dto = Hydrator::default()->hydrate([
    'items' => [['kind' => 'known', 'city' => 'Sample'], ['kind' => 'future', 'enabled' => false]],
], RecordsDto::class);
```

Missing сохраняет constructor default null, пустой list принимается.
Явный null, ассоциативный массив и разреженные индексы отклоняются с путём `items`.
После проверки формы `Nested` создаёт известный DTO и сохраняет неизвестный вариант
по умолчанию `KeepRaw`. Неверный тип `city` у известного элемента даёт `items[0].city`.

Порядок обработки состояния:

1. Поиск значения и fallback.
2. Нормализация пустой строки, если включена политикой или `EmptyStringAsNull`.
3. Применимый `DefaultValue` с текущими `$value` и `$state`; `$source` содержит данные текущего DTO.
4. Встроенный fallback missing typed collection, затем `Nested` или casts и проверка типа.

По умолчанию действует `Keep`: provider видит исходную пустую строку и `Present`.
При преобразовании пустой строки в null он видит `Null`.
Если на свойстве есть `#[Cast]`, нормализация пустой строки пропускается даже
при `EmptyStringAsNull`; provider выполняется до cast.

Для отказа используйте структурированную `HydrationException::invalidValue()`:
гидратор добавит имя текущего поля, родителей, индексы и `Returns.unwrap`.
Provider задаёт только локальный суффикс пути, если он нужен; `reason`, `expected`,
`actual` и цепочка `previous` сохраняются. Не включайте значения ответа в текст ошибки.
`ConfigurationException` остаётся ошибкой конфигурации. У исключений
без `reason` автоматическое дополнение пути не выполняется.

Ошибка provider поля `count` внутри `child` имеет путь `child.count`,
или `data.child.count` после unwrap `data`.

## Внешние правила поля <a id="section-10"></a>

Порядок поля: поиск → required/null/форма → нормализация пустой строки → default →
обработка Missing → преобразование → проверка native-типа → единственный вызов конструктора.
Missing/null не проверяются как контейнер. `required()` проверяется до любого default,
в том числе автоматического пустого typed collection. `forbidExplicitNull()` действует
до нормализации и не запрещает null, полученный из пустой строки при NullIfEmpty.
По умолчанию действует Keep; cast всего поля пропускает нормализацию пустой строки.

`DefaultSpec::value($value, ValueState ...$when)` и
`DefaultSpec::provider(HandlerSpec $provider, ValueState ...$when)` без when действуют
при Missing. Для проверки найденного значения укажите Present, для обоих состояний —
Null и Present. Provider вызывается на каждом применении и может создать независимый объект.
Literal value и `HandlerSpec::args` допускают scalar, null, unit/backed enum case и массивы
из них любой глубины; прочие объекты и closure внутри массивов запрещены.
Enum case сохраняет идентичность. [Defaults конструктора](lifecycle.md#section-3)
по-прежнему вычисляются PHP только при отсутствии аргумента.

У [constructorValue](constructor-values.md) default/provider обрабатывается до
сравнения с конструктором. `allowMissing` разрешает отсутствие, но не отменяет required.
