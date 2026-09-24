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
`errors` — причины и пути ошибок, `conflictRejected` — отказ при пересечении деклараций.

[Выбрать способ описания DTO](../start/describe-dto.md) · [Все примеры](README.md).
