<!-- languages --> <a href="../../en/examples/dto-showcase.md">English</a> · <a href="dto-showcase.md">Русский</a> <!-- /languages -->
# Каталог возможностей DTO <a id="section-1"></a>

Один вымышленный товар показывает атрибуты и общую policy, вложенные модели,
типизированную коллекцию, варианты элементов, файл Base64/data URI, custom cast, provider, extras и ошибки.
Пошаговое объяснение и таблицы результатов — в [обзоре DTO](../guides/dto/showcase.md).

## Запуск <a id="section-2"></a>

Из checkout после `composer install`:

```bash
php docs/example/dto-showcase/run.php
```

Из проекта с установленным пакетом:

```bash
php vendor/apisutra/php/docs/example/dto-showcase/run.php
```

Сеть и ключи API не нужны: MockTransport записывает запросы и возвращает фикстуру.
Запуск сравнивает standalone и Returns, отправляет DTO через BodyRoot и собирает
десять ошибок гидратации. Файл превращается в `Base64File` с содержимым `SDK manual`;
при `toArray()` и отправке возвращается чистый Base64 без data URI-префикса.
[Ожидаемые значения](../../example/dto-showcase/fixtures/expected.json) заданы отдельно;
проверка документации сверяет их с результатом и контролирует совпадение деклараций в обзоре с кодом.

## Исходники <a id="section-3"></a>

| Файл | Назначение |
| --- | --- |
| [CatalogItemDto](../../example/dto-showcase/src/CatalogItemDto.php) | Основная модель с комментариями к каждому приёму |
| [CatalogHydration](../../example/dto-showcase/src/CatalogHydration.php) | Общий Strict без реестра моделей |
| [SellerDto](../../example/dto-showcase/src/SellerDto.php) | Вложенный plain DTO с собственным остатком |
| [TagDto](../../example/dto-showcase/src/TagDto.php), [TagCollection](../../example/dto-showcase/src/TagCollection.php) | Типизированная коллекция |
| [ImageDto](../../example/dto-showcase/src/ImageDto.php), [VideoDto](../../example/dto-showcase/src/VideoDto.php) | Два варианта media |
| [ItemStatus](../../example/dto-showcase/src/ItemStatus.php) | Backed enum |
| [MinorUnitsCast](../../example/dto-showcase/src/MinorUnitsCast.php) | Точное преобразование строки цены в целые сотые и обратно |
| [DisplayNameProvider](../../example/dto-showcase/src/DisplayNameProvider.php) | Вычисление отсутствующего значения из исходных данных |
| [CatalogClient](../../example/dto-showcase/src/CatalogClient.php) | Клиент с той же общей policy |
| [GetCatalogItemRequest](../../example/dto-showcase/src/GetCatalogItemRequest.php) | Returns с unwrap |
| [SaveCatalogItemRequest](../../example/dto-showcase/src/SaveCatalogItemRequest.php) | Сериализация DTO в тело запроса |
| [item.json](../../example/dto-showcase/fixtures/item.json), [expected.json](../../example/dto-showcase/fixtures/expected.json) | Входные данные и ожидаемое поведение |
| [run.php](../../example/dto-showcase/run.php) | Сборка, преобразования, сравнение результатов и ошибочные сценарии |

Формат вывода: `dto` — значения и типы через наблюдаемые свойства, `dx` — toArray(),
`wire` — JSON отправленного запроса, `fallback` — запасной ключ и defaults,
`errors` — причины и пути ошибок.

## Варианты DTO и исходный JSON <a id="json-mapping"></a>

[Учебный Records SDK](sdk.md) дополняет каталог механизмами 0.3: варианты на типе,
типизированный fallback, проверка формы перед cast и публичный JSON-вход для webhook.
Запуск из установленного пакета:

```bash
php vendor/apisutra/php/docs/example/sdk/run.php
```

В [исходном ответе](../../example/sdk/fixtures/record.json) список `assets` содержит
изображение, документ и пока неизвестный SDK тип `audio`. Поле `cover` — одиночное изображение.
Одна декларация на [общем типе вложений](../../example/sdk/src/Resources/Records/Get/Dto/AttachmentDto.php)
обслуживает оба поля и корневой JSON:

```php
namespace Example\Records\Resources\Records\Get\Dto;

use ApiSutra\Attributes\DataTransfer\DtoVariants;
use ApiSutra\DataTransfer\AbstractDto;

#[DtoVariants(
    discriminator: 'type',
    map: ['image' => ImageAttachmentDto::class, 'document' => DocumentAttachmentDto::class],
    unknown: RawAttachmentDto::class,
)]
abstract readonly class AttachmentDto extends AbstractDto {}
```

Классы вариантов из этого namespace находятся в том же каталоге SDK.
`ImageAttachmentDto` и `DocumentAttachmentDto` наследуют общий тип и описывают известные поля.
[RawAttachmentDto](../../example/sdk/src/Resources/Records/Get/Dto/RawAttachmentDto.php) тоже наследует его,
но собирает весь неизвестный узел в `#[Extras] public array $raw` — включая type, false, null и пустые списки.
Ошибка поля известной модели остаётся ошибкой: fallback предназначен для неизвестного тега.

| Место в модели ответа | Объявление и результат |
| --- | --- |
| assets | `ListShape(new DtoShape(AttachmentDto::class), each: 'value')` извлекает узел из каждой обёртки; rank остаётся в extras |
| cover | `?AttachmentDto` выбирает вариант по той же карте без Shape и отдельного cast |
| localized_titles | `InputShape(ContainerShape::Object)` проверяет объект до LocalizedTitlesCast; cast проверяет языковые коды и строковые названия |

Полные атрибуты и импорты — в [модели ответа](../../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php).
Для собственных моделей атрибута достаточно: реестр в конфиге не нужен.
Если менять класс нельзя, [HydrationRules::withVariants()](../reference/dto/variants.md) задаёт ту же декларацию
во внешних правилах HydrationConfig. На одном типе выбирают один из этих способов.

Для webhook не надо заранее вызывать `json_decode()`: он потеряет различие пустых `{}` и `[]`
при преобразовании в ассоциативные массивы. После загрузки bootstrap.php примера:

```php
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Serialization\Hydrator;
use Example\Records\Config\ClientConfigFactory;
use Example\Records\Resources\Records\Get\Dto\AttachmentDto;
use Example\Records\Webhook\RecordPayload;

$config = ClientConfigFactory::create(); // Эта же конфигурация передаётся HTTP-клиенту.
$hydrator = Hydrator::forConfig($config->hydration ?? new HydrationConfig(), $config->localization);
// $rawJson — исходное тело webhook с оболочкой {"data": {...}}.
$payload = $hydrator->hydrateJson($rawJson, RecordPayload::class);
$record = $payload->data;
$unknown = $hydrator->hydrateJson('{"type":"audio","duration":12}', AttachmentDto::class);
// $unknown — RawAttachmentDto; raw содержит type и duration.
```

Проверка JSON-формы включена по умолчанию; [общая настройка](../reference/dto/configuration.md)
действует одинаково для HTTP и hydrateJson(). В примере семнадцать ошибочных тел проходят
через оба входа, а вывод показывает их reason и sourcePath рядом:

| Данные | Результат |
| --- | --- |
| Неизвестный `type: "audio"` | RawAttachmentDto; значение сохраняется и при toArray() |
| Известный `type: "image"`, но `width: "640"` | Отказ строгой проверки int, без перехода в fallback |
| `type: true` | invalid_discriminator_type |
| `localized_titles: []` | invalid_object_shape до cast; пустой объект `{}` допустим |
| `localized_titles: {"en": 17}` | invalid_localized_titles из cast |
| `related_ids: {}` или `{"0": 11, "1": 12}` | invalid_list_shape; список требуется и для числовых ключей |
| `cover: []` | Отказ формы DTO с sourcePath `/data/cover` |

`raw` хранит PHP-значения, а не исходные байты или сведения о форме каждого JSON-узла.
`toArray()` следует правилам сериализации DTO: неизвестное вложение здесь представлено объектом с полем raw.
Для гидратации уже готовых PHP-данных остаётся [hydrate()](../reference/dto/configuration.md#json-input);
при передаче нового массива из cast исходная JSON-форма не переносится автоматически.

[Выбрать способ описания DTO](../start/describe-dto.md) · [Все примеры](README.md).
