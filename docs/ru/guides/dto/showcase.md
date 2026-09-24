<!-- languages --> <a href="../../../en/guides/dto/showcase.md">English</a> · <a href="showcase.md">Русский</a> <!-- /languages -->
# Возможности DTO на одном примере <a id="section-1"></a>
`CatalogItemDto` описывает товар: маппинг, defaults, преобразования, вложенность, коллекции, Base64-файл и исходящий JSON.

[Входной JSON](#section-2) · [DTO](#section-3) · [Политика](#section-4) ·
[Результат](#section-5) · [Сериализация](#section-6) · [Файл в DTO](#section-7) ·
[Отправка](#section-8) · [Ошибки](#section-9) · [Другие варианты](#section-10).

Из checkout после `composer install`:

```bash
php docs/example/dto-showcase/run.php
```

В установленном пакете добавьте к пути `vendor/apisutra/php/`. MockTransport работает без сети. [Ожидаемый результат](../../../example/dto-showcase/fixtures/expected.json): DTO, DX, HTTP payload и ошибки.

## Входной JSON <a id="section-2"></a>

Это [поле `data`](../../../example/dto-showcase/fixtures/item.json) успешного ответа API: без displayName и stock, с title=null.

```json
{
  "kind": "catalog_item",
  "product_id": 7,
  "vendor_code": "BK-7",
  "title": null,
  "description": "   ",
  "available": true,
  "metrics": {"rating": 4.8, "votes": 12},
  "created_at": "2026-09-15T10:30:00+00:00",
  "state": "active",
  "price": "12.34",
  "manual_file": "data:text/plain;base64,U0RLIG1hbnVhbA==",
  "seller": {"id": 9, "name": "Книжная лавка", "tier": "gold"},
  "tags": [{"name": "php"}, {"name": "sdk"}],
  "related_ids": [11, 12],
  "assets": [
    {"value": {"type": "image", "url": "https://assets.example.test/cover.png", "width": 640}, "rank": 1},
    {"value": {"type": "video", "url": "https://assets.example.test/demo.mp4", "duration": 30}, "rank": 2}
  ],
  "future_flag": false
}
```

## Декларация DTO <a id="section-3"></a>

Полный [CatalogItemDto](../../../example/dto-showcase/src/CatalogItemDto.php): `AbstractDto` даёт `from()`, `toArray()`, `with()` и подходит для `Returns`.
`AbstractResponseDto` добавляет `computed()`; [обычные PHP-классы](plain-models.md) также поддерживаются.

```php
declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\DtoSerialize;
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\Casts\DataUriBase64FileCast;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Enums\Serialization\EnumOutput;
use ApiSutra\VO\Files\Base64File;
use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Attributes\DataTransfer\RequiredInput;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\ForbidExplicitNull;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\ListShape;
use ApiSutra\Serialization\Shapes\VariantsShape;
use DateTimeImmutable;

// toArray() сохраняет null и строковые значения enum.
#[DtoSerialize(enumOutput: EnumOutput::Value, serializeNulls: true)]
final readonly class CatalogItemDto extends AbstractDto
{
    // Значение конструктора проверяется по входу без повторной записи в readonly.
    #[ConstructorValue]
    public string $kind;

    /**
     * @param list<int> $relatedIds
     * @param list<ImageDto|VideoDto> $media
     * @param array<string, mixed> $_extra
     */
    public function __construct(
        // Входное имя с запасным путём; исходящее имя задаётся отдельно.
        #[From('product_id', fallback: ['id'])]
        #[To('product_id')]
        #[RequiredInput]
        public int $id,
        // Одно внешнее имя для чтения и записи.
        #[Map('vendor_code')]
        public string $sku,
        // Отсутствие и явный null разрешены контрактом вымышленного API.
        #[DefaultValue('Без названия', when: [ValueState::Missing, ValueState::Null])]
        public string $title,
        // Ключ обязателен, но его значение может быть null или пустой строкой.
        #[EmptyStringAsNull(blank: true)]
        public ?string $description,
        // Общая Strict-policy проверяет точный тип bool.
        public bool $available,
        // Вложенный путь можно развернуть в отдельное свойство DTO.
        #[From('metrics.rating')]
        public float $rating,
        // Вход содержит время с часовым поясом, исходящий формат — календарную дату.
        #[From('created_at')]
        #[To('created_at')]
        #[DateTimeFrom(format: DATE_ATOM, strictFormat: true)]
        #[DateTimeTo(format: 'Y-m-d', timezone: 'UTC')]
        public DateTimeImmutable $createdAt,
        // Строковое значение API превращается в backed enum.
        #[From('state')]
        #[To('state')]
        public ItemStatus $status,
        // Собственный cast читает "12.34" как 1234 и выполняет обратное преобразование.
        #[From('price')]
        #[To('price')]
        #[Cast(MinorUnitsCast::class)]
        public int $priceMinor,
        // Файл внутри JSON: вход допускает data URI, выход содержит чистый Base64.
        #[Map('manual_file')]
        #[Cast(DataUriBase64FileCast::class)]
        public Base64File $manual,
        // SellerDto — обычный PHP-класс; Nested создаёт отдельный вложенный объект.
        #[Nested(type: SellerDto::class)]
        public SellerDto $seller,
        // Элементы становятся DTO, контейнер проверяет их тип и даёт first()/count().
        #[Nested(type: TagDto::class)]
        public TagCollection $tags,
        // Shape проверяет каждый элемент списка; PHPDoc нужен для IDE.
        #[From('related_ids')]
        #[To('related_ids')]
        #[RequiredInput]
        #[Shape(new ListShape(ScalarType::Int))]
        public array $relatedIds,
        // Каждый value становится DTO варианта; соседний rank остаётся в extras.
        #[From('assets')]
        #[To('assets')]
        #[RequiredInput]
        #[Shape(new ListShape(new VariantsShape('type', [
            'image' => ImageDto::class,
            'video' => VideoDto::class,
        ], unknown: NestedUnknownVariant::Error), each: 'value'))]
        public array $media,
        // Provider вычисляет отсутствующее значение по исходным данным DTO.
        #[DefaultValue(provider: DisplayNameProvider::class)]
        public string $displayName,
        // Отсутствие разрешено; исходный null запрещён.
        #[ForbidExplicitNull]
        public ?int $stock = null,
        // Непрочитанные поля сохраняются здесь и исключаются из запросов клиента.
        #[Extras]
        public array $_extra = [],
    ) {
        $this->kind = 'catalog_item';
    }
}
```

Вспомогательные типы: [SellerDto](../../../example/dto-showcase/src/SellerDto.php) — обычный класс; [TagDto](../../../example/dto-showcase/src/TagDto.php) и [TagCollection](../../../example/dto-showcase/src/TagCollection.php) — типизированная коллекция; [ImageDto](../../../example/dto-showcase/src/ImageDto.php) / [VideoDto](../../../example/dto-showcase/src/VideoDto.php) — варианты; [ItemStatus](../../../example/dto-showcase/src/ItemStatus.php) — enum.

[MinorUnitsCast](../../../example/dto-showcase/src/MinorUnitsCast.php): строка цены ↔ целые сотые; до семи цифр перед точкой и ровно две после. [DisplayNameProvider](../../../example/dto-showcase/src/DisplayNameProvider.php) вычисляет `Товар BK-7` по vendor_code из исходных данных DTO.

## Общая политика клиента <a id="section-4"></a>

[CatalogHydration](../../../example/dto-showcase/src/CatalogHydration.php) задаёт общий Strict.
Все декларации полей, включая вложенный receiver, находятся на моделях; реестр DTO не нужен.

```php
declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;

final class CatalogHydration
{
    public static function create(): HydrationConfig
    {
        return new HydrationConfig(policy: new RulePolicy(scalars: ScalarPolicy::Strict));
    }
}
```

В [run.php](../../../example/dto-showcase/run.php) `$source` содержит JSON, `$hydration = CatalogHydration::create()`.
Standalone: `Hydrator::forConfig($hydration)->hydrate($source, CatalogItemDto::class)`.
Тот же блок передаётся в `ClientConfig(hydration: $hydration, ...)`; [запрос](../../../example/dto-showcase/src/GetCatalogItemRequest.php)
объявляет `Returns(CatalogItemDto::class, unwrap: 'data')`.
`CatalogItemDto::from()` читает атрибуты, но общую политику клиента не наследует.

[Внешние правила](plain-models.md) полезны для чужих моделей. Не дублируйте ими
входной атрибут того же поля: это [конфликт конфигурации](../../reference/dto/field-rules.md#section-3).

## Что получит приложение <a id="section-5"></a>

| Свойство | Вход / условие | Механизм → результат |
| --- | --- | --- |
| `kind` | `"catalog_item"` | ConstructorValue проверяет фиксированное значение конструктора |
| `id` | `product_id: 7` | From → `7`; при отсутствии ключа проверяется `id`, найденный null не включает fallback |
| `sku` | `vendor_code: "BK-7"` | Map → `"BK-7"`, то же внешнее имя используется при записи |
| `title` | null или ключ отсутствует | DefaultValue → `"Без названия"` |
| `description` | `" "` | EmptyStringAsNull → null; обычный текст сохраняется; отсутствие ключа — ошибка |
| `available` | true | Strict → bool; строка `"true"` не заменяет bool |
| `rating` | `metrics.rating: 4.8` | From с вложенным путём → float; соседний votes остаётся в `_extra` |
| `createdAt` | строка с датой и смещением | DateTimeFrom → DateTimeImmutable, дата остаётся объектом до сериализации |
| `status` | `state: "active"` | Native enum → `ItemStatus::Active` |
| `priceMinor` | `price: "12.34"` | Cast → `1234`; при сериализации снова `"12.34"` |
| `manual` | data URI в manual_file | Cast → Base64File; `content()` возвращает `"SDK manual"`, `size()` — 10 байт |
| `seller` | объект с id/name/tier | Nested → SellerDto; непрочитанный tier сохраняется в `seller->_extra` |
| `tags` | два объекта с name | Nested → TagCollection с двумя TagDto; отсутствие поля даёт пустую коллекцию |
| `relatedIds` | `[11, 12]` | ValueShape::list(int) → список int; строка внутри списка даёт ошибку |
| `media` | `assets[*].value` | each + variants → ImageDto и VideoDto; неизвестный type даёт ошибку |
| `displayName` | ключ отсутствует | Provider → `"Товар BK-7"` |
| `stock` | ключ отсутствует / 0 / null | Получится null / 0 / ошибка соответственно |
| `_extra` | metrics.votes, future_flag и rank рядом с value | Остаток данных, не прочитанный полями; не создаёт динамических свойств |

У `_extra` получается такой [остаток](../../reference/dto/extras.md):

```json
{"metrics":{"votes":12},"assets":[{"sourceKey":0,"remainder":{"rank":1}},{"sourceKey":1,"remainder":{"rank":2}}],"future_flag":false}
```

Остаток seller принадлежит SellerDto; discriminator прочитан ImageDto/VideoDto. Прочитанный null удаляется, непрочитанный false сохраняется.

## Атрибуты сериализации <a id="section-6"></a>

To/Map задают имена, DateTimeTo и Cast — представление значений; DtoSerialize настраивает dump.
[Полный контракт DX/wire](../../reference/serialization/dto-output.md); результат обоих путей — в таблице отправки ниже.

## Файл в поле DTO <a id="section-7"></a>

`manual` выше — инструкция внутри JSON. `DataUriBase64FileCast` принимает чистый Base64 и data URI:
`$item->manual->content()` даёт `"SDK manual"`; `$item->manual->saveTo($path)` сохраняет эти байты в указанный файл.
При `toArray()` и отправке Cast возвращает `manual_file: "U0RLIG1hbnVhbA=="` без MIME-префикса data URI.
Base64 материализуется в памяти. Потоковые upload/download показаны в [файловом рецепте](../recipes/files.md).
[Контракт Base64File и списки файлов](../../reference/files/downloads.md#section-8).

## Что уйдёт в запрос <a id="section-8"></a>

[SaveCatalogItemRequest](../../../example/dto-showcase/src/SaveCatalogItemRequest.php) передаёт объект через `BodyRoot`; MockTransport записывает JSON.

| Значение | `$item->toArray()` — DX | JSON запроса клиента — wire |
| --- | --- | --- |
| `id` / `sku` | product_id / vendor_code | product_id / vendor_code |
| Дата / enum / цена | `"2026-09-15"` / `"active"` / `"12.34"` | Те же значения |
| `manual` | Чистый Base64 под именем manual_file | Та же строка без data URI-префикса |
| `description` / `stock` | null сохраняется благодаря DtoSerialize | Ключи опущены стандартной wire-политикой |
| `_extra`, включая seller | Обычное свойство с остатком | Исключено атрибутами Extras на обеих глубинах |
| `media` | Массив объектов под именем assets | Массив без входной обёртки value и без rank |

Входной `each` не восстанавливает обёртку; `toArray()` не гарантирует побайтовый round-trip JSON.
Настройки [DX и wire](../../reference/serialization/dto-output.md) выбираются по контракту API.
Исключение receiver зависит от декларации класса, включая DTO, созданные вручную: [границы casts и представлений](../../reference/serialization/receiver-output.md).

## Как выглядят ошибки <a id="section-9"></a>

`run.php` отдельно гидратирует десять повреждённых вариантов. `path` указывает свойство DTO,
`sourcePath` — JSON Pointer во входе standalone; при Returns внешний unwrap добавляет `/data`.

| Нарушение | reason | path | sourcePath |
| --- | --- | --- | --- |
| Неверный kind | constructor_value_mismatch | kind | /kind |
| Нет обоих имён id | required_field_missing | id | /product_id (expected) |
| `product_id: "7"` | invalid_field_type | id | /product_id |
| `product_id: null`, при этом `id: 8` | null_not_allowed | id | /product_id |
| `stock: null` | explicit_null_not_allowed | stock | /stock |
| `related_ids: [11, "12"]` | invalid_field_type | relatedIds[1] | /related_ids/1 |
| Строковый seller.id | invalid_field_type | seller.id | /seller/id |
| Неизвестный type варианта | unknown_nested_variant | media[0] | /assets/0/value |
| Дата не соответствует формату | invalid_datetime | createdAt | /created_at |
| Цена `"12,34"` | invalid_price (свой cast) | priceMinor | /price (boundary) |

Boundary у cast указывает вход преобразования; для computed и непрозрачных преобразований точный источник может быть недоступен.
[Диагностика и безопасный лог](../../reference/dto/diagnostics.md) описывают границы точности.

## Как выбрать другой приём <a id="section-10"></a>

| Задача | Вариант и граница |
| --- | --- |
| DTO уже существует и не должен зависеть от ApiSutra | [Обычный PHP-класс + HydrationRules](plain-models.md); примеры форм работают без базового DTO |
| Одинаковые имена, даты и casts во многих моделях | [NamingStrategy и профиль гидратации](../../reference/dto/profiles.md); профиль — альтернатива DtoRules для этого класса |
| Правила вложенного списка удобнее хранить в DTO | [Nested с each/discriminator](../../reference/dto/shapes.md); не совмещать с FieldRule того же свойства |
| Нужны словари, вложенные списки, union или допустимый null элемента | [ValueShape и строгие скаляры](../../reference/dto/scalars.md), [формы](../../reference/dto/shapes.md); PHPDoc сам не валидирует элементы |
| Неизвестные варианты надо сохранять или пропускать | [KeepRaw / Skip](../../reference/dto/variants.md); KeepRaw требует контейнера, допускающего raw-значения |
| Cast/provider сам создаёт вложенные DTO | [HydrationContext](../../reference/dto/scope.md) передаёт текущие правила; простой Hydrator::default() их теряет |
| Default зависит от контекста или нужна проверка найденного значения | [DefaultValue provider и состояния](../../reference/dto/defaults.md#section-9); один provider может обработать несколько состояний |
| Нужны прикладные правила и описание смысла поля | [Validate, Label, About](../../reference/attributes/hydration.md); подключение валидатора — [отдельный шаг](../../reference/client/validation.md) |

[Выбрать DTO для своего SDK](../../start/describe-dto.md) · [Полный справочник](../../reference/dto/README.md) · [Исходники и запуск](../../examples/dto-showcase.md).
