<!-- languages --> <a href="../../../en/reference/dto/shapes.md">English</a> · <a href="shapes.md">Русский</a> <!-- /languages -->
# Вложенные объекты и списки <a id="section-1"></a>

`#[Shape(new ListShape(ScalarType::Int))]` объявляет строгий список; NullableShape, DtoShape и VariantsShape составляют рекурсивные формы. Это атрибутный синтаксис того же ValueShape, без отдельного механизма исполнения. Shape вместе с Nested или Cast того же поля отклоняется.

Для одиночного вложенного DTO с конкретным классом в native-типе есть более короткая запись:

| Объявление | Как выбирается класс вложенного DTO |
| --- | --- |
| `#[Shape(new DtoShape(AddressDto::class))] public AddressDto $address;` | Явно в DtoShape |
| `#[Nested] public AddressDto $address;` | Из типа свойства |

Для такого простого поля удобно использовать `Nested`, чтобы не повторять класс.
`Shape` удобен для составных форм: строгих списков, вложенных списков и сочетаний
DTO, nullable-значений и вариантов объектов. Две строки таблицы — альтернативные
объявления одного поля, их не нужно совмещать.

## Вложенные DTO <a id="section-2"></a>
Когда ответ содержит вложенные объекты или списки объектов (например, `user.address`, `order.items[]`).

Одиночный объект может быть plain DTO без базового класса ApiSutra:

```php
<?php

declare(strict_types=1);

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Serialization\Hydrator;

final readonly class AddressDto
{
    public function __construct(public string $city)
    {
    }
}

final readonly class UserDto
{
    #[Nested(type: AddressDto::class)]
    public AddressDto $address;
}

$payload = json_decode('{"address":{"city":"Sample"}}', true, flags: JSON_THROW_ON_ERROR);
$user = Hydrator::default()->hydrate($payload, UserDto::class);
echo $user->address->city; // Sample
```

То же объявление работает с constructor promotion и через `Returns`.
Для одиночного свойства конкретный класс можно вывести из native-типа:
`#[Nested] public AddressDto $address`. Для массива `type` задаёт класс элемента.
Правила выбора объекта/списка, nullable/union и все параметры —
в [справочнике Nested](../attributes/hydration.md#section-12).

Пример key‑mode (кейс вида `{"person": {...}}`):
```php
use ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;

#[Nested(
    discriminatorMode: NestedDiscriminatorMode::Key,
    map: [
        'person' => PersonOwnerDto::class,
        'organization' => OrganizationOwnerDto::class,
    ],
    unknownVariant: NestedUnknownVariant::KeepRaw,
)]
public array $owners = [];
```

Если nested-массив уже найден правильно, но каждый его элемент нужно отдельно преобразовать, используйте `itemCast`:

```php
use ApiSutra\Casts\DataUriBase64FileCast;
use ApiSutra\VO\Files\Base64File;

#[Nested(
    type: Base64File::class,
    itemCast: DataUriBase64FileCast::class,
)]
public array $faces = [];
```

Правило:
- `Cast` на свойстве — transform всего значения свойства
- `Nested(itemCast: ...)` — transform каждого элемента массива

## Формы, присутствие и defaults <a id="section-3"></a>

`ValueShape` предоставляет `int()`, `float()`, `bool()`, `true()`, `false()`, `string()`,
`mixed()`, `scalars(ScalarType ...$types)`, `nullable(ValueShape $shape)`, `dto(string $class, bool $emptyListAsObject = false)`,
`list(ValueShape $item, ?string $each = null, ?HandlerSpec $itemCast = null, bool $normalizeKeys = false)`.
Списки могут быть вложенными. PHPDoc `list<int>` и bare `array` сами элементы не проверяют.
Plain native-класс без `dto()`, Nested или DtoInterface не гидратируется автоматически.

`list()` требует плотные ключи 0..n−1; словарь и разреженный массив дают
`invalid_list_shape`. `normalizeKeys: true` разрешает словарь и переиндексирует результат,
сохраняя исходные ключи в диагностике и extras. Traversable не считается списком.
По умолчанию стандартный путь JSON-ответа сохраняет исходные виды контейнеров
до проверки корня и всех вложенных DTO. Механизм управляется единой настройкой
клиента [jsonShapeValidation](configuration.md#section-4):

| JSON на входе | Строгий список | DTO без обязательных полей |
| --- | --- | --- |
| `[]` | Принимается | `invalid_object_shape` |
| `{}` | `invalid_list_shape` | Принимается |
| `{"0":"a","1":"b"}` | `invalid_list_shape` | Объект; далее действуют правила полей DTO |
| `["a"]` | Принимается для строковых элементов | `invalid_object_shape` |

Например, `{"items":[]}` соответствует `ListShape(ScalarType::String)`, а
`{"items":{}}` даёт FAILED / `hydration_error`, reason `invalid_list_shape`,
по пути `items`. Это работает и при cache hit, и в async. Missing, явный null,
пустой объект и пустой список остаются различимыми до соответствующих проверок.

Если провайдер использует пустой список вместо конкретного объекта, разрешите
это преобразование локально:

```php
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\DtoShape;

#[Shape(new DtoShape(AddressDto::class, emptyListAsObject: true))]
public AddressDto $address;
```

Та же опция есть у `ValueShape::dto()` и [Returns](../attributes/response.md#section-4).
Она разрешает **только пустой список для данного DTO**. Обязательные поля проверяются;
непустые списки, null, дочерние и соседние узлы разрешение не наследуют. У Returns
оно относится к выбранному DTO после unwrap/type. Явный
`FieldRule::inputShape(InputShape::Object)` проверяет исходный вход раньше и вправе
отклонить `[]`; result shape после cast по-прежнему требует готовый DTO. Если это
локальное исключение нужно одиночному Nested, используйте вместо него Shape.

Standalone `Hydrator::hydrate()` / `DTO::from()` и созданные пользователем PHP-данные
сохраняют PHP-контракт: DTO принимает объект или массив формы object, а пустой
PHP-массив неоднозначен. SDK не восстанавливает форму, уже потерянную при decode
в приложении. [Пользовательское преобразование](scope.md#section-3) создаёт новый
PHP-вход. Публичные json()/jsonStrict(), поля DTO, extras и toArray() сохраняют
свои типы; сохранение вида контейнера при последующем JSON round-trip не гарантируется.

Присутствие, исходный null, нормализация и default проверяются по
[порядку обработки поля](defaults.md#section-10).

В списке порядок — each → itemCast → форма элемента. Готовый DTO от itemCast или provider
не гидратируется повторно; `cast(..., result: ...)` проверяет готовое значение.
У атрибутного Nested cast всего поля игнорируется.

## Nested: одиночный объект и коллекция <a id="section-4"></a>

Гидратор выбирает назначение по объявлению свойства, до разбора его данных:

| Объявление | Обработка |
| --- | --- |
| `#[Nested] AddressDto` или `?AddressDto` | Одиночный DTO указанного класса |
| `#[Nested(type: AddressDto::class)] AddressDto` | Одиночный DTO; допустим также конкретный подтип |
| Интерфейс или абстрактный класс свойства | Для одиночного объекта нужен совместимый конкретный `Nested.type` |
| `object` | Для одиночного объекта нужен конкретный `Nested.type` |
| `array`, `iterable`, `mixed`, отсутствие native-типа | Сохраняется обработка массива элементов; `type` задаёт класс элемента |
| Класс `Traversable`, включая `AbstractCollection` / `AbstractTypedCollection` | Коллекция; `type` задаёт класс элемента, `map` — классы вариантов |
| Пользовательская обёртка и отдельный, несовместимый с ней `type` элемента | Коллекция, если есть вызываемая `fromArray()` либо публичный конструктор, принимающий массив первым аргументом без других обязательных аргументов |
| Фабричная обёртка с `map` без `type` | Коллекция через `fromArray()` |
| Union только классов/интерфейсов | Для одиночного объекта обязателен `type`, совместимый хотя бы с одной веткой |

`null` не выбирает ветку union. Union с разной кардинальностью, например
`AddressDto|array`, union без необходимого `type`, несовместимый класс, недоступный
конструктор или intersection дают `ConfigurationException`. Параметры списка
`each`, `itemCast`, `discriminator`, `map` несовместимы с одиночным объектом.
Эта проверка выполняется при обработке ненулевого найденного значения.

Одиночный DTO принимает ассоциативный массив или PHP-объект. Гидратация проходит
через обычные проверки полей и один вызов конструктора, включая готовый PHP DTO.
Scalar и непустой PHP list дают `unexpected_response_shape` по пути свойства.
Пустой массив проходит проверку полей дочернего DTO: например, отсутствие `city`
даёт `required_field_missing` с путём `address.city`.
При включённой проверке JSON-формы одиночный Nested отклоняет любой JSON array,
включая `[]`; пустой JSON object проходит к проверкам полей. Коллекции Nested
сохраняют контракт с поддержкой карт. [Исполняемый пример](shapes.md#section-2).

`Nested.from` переопределяет путь `From` / `Map` / имени свойства.
Непустой `Nested.fallback` имеет приоритет над `From.fallback`; fallback применяется
только при отсутствии ключа, а найденный null сохраняется. `DefaultValue`,
constructor default, nullable и пустая typed collection сохраняют
[общие правила missing/null](defaults.md#section-2).

Для списков порядок остаётся `each` → `itemCast` → гидратация элементов или
discriminator → обёртка коллекции. `Nested` не включает проверку PHPDoc `list<T>`;
форма списка и scalar-элементы требуют явных проверок. Ошибка элемента содержит
его порядковый индекс. `#[Cast]` всего свойства при наличии `Nested` не выполняется.

Ошибка одиночного объекта содержит путь поля дочернего DTO без индекса списка.
Укажите конкретный класс DTO и native-тип, различающий объект и коллекцию.
