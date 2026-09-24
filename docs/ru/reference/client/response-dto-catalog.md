<!-- languages --> <a href="../../../en/reference/client/response-dto-catalog.md">English</a> · <a href="response-dto-catalog.md">Русский</a> <!-- /languages -->
# Каталог response DTO <a id="section-1"></a>

## Каталог response DTO по мегаклиенту <a id="section-2"></a>
Если нужен один общий перечень всех DTO, которые возвращают сервисы мегаклиента
(sync через `Returns`, async-final через `ContinuationResult`, download через
`#[Download]`), apisutra даёт готовый multi-service слой поверх
[`ResponseDtoCatalog`](response-dto-catalog.md#section-9).

### Через factory (канонический путь) <a id="section-3"></a>
```php
use ApiSutra\OperationInventory\Catalog\MultiServiceResponseDtoCatalogFactory;

$catalog = (new MultiServiceResponseDtoCatalogFactory())->fromMultiService($mega);

foreach ($catalog->usages() as $dtoClass => $usages) {
    foreach ($usages as $usage) {
        $usage->serviceClass;   // FQCN сервис-клиента (например, RealtyClient::class)
        $usage->resourceLabel;  // 'Reports / Tasks' внутри сервиса
        $usage->kind;           // ResponseDtoKind::Sync | AsyncFinal | Download
    }
}
```

### Унифицированный обход разнородных провайдеров <a id="section-4"></a>

Потребитель типизирует готовый каталог через `ResponseDtoCatalogProviderInterface`;
контракт и ограничения описаны ниже в разделе «Унифицированный контракт».

### Через trait (sugar для мегаклиента) <a id="section-5"></a>
Чтобы получить `$mega->responseDtoCatalog()` без бойлерплейта, подмешайте trait:

```php
use ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;
use ApiSutra\Traits\ProvidesMultiServiceResponseDtoCatalogTrait;

final class MegaClient implements MultiServiceClientInterface
{
    use ProvidesMultiServiceResponseDtoCatalogTrait;

    public function services(): array { /* ... */ }
}

$catalog = $mega->responseDtoCatalog();   // кешируется на инстансе
```

Trait — тонкий wrapper над factory, ничего больше. `MultiServiceClientInterface`
осознанно остаётся одно-методным; этот trait — DX-слой, не часть контракта.

### Экспорт в файл <a id="section-6"></a>
Built-in `MarkdownResponseDtoCatalogExporter` автоматически распознаёт
multi-service режим (по наличию `serviceClass` в usages) и:

- добавляет секцию `## Сервисы` со счётчиками `Sync / AsyncFinal / Download` по сервисам
- добавляет колонку `Service` во все таблицы (по ресурсам, by-DTO usages, downloads)

Single-client output (когда `serviceClass` ни у кого не заполнен) остаётся
полностью прежним — никакого drift.

```php
use ApiSutra\OperationInventory\Catalog\Export\MarkdownResponseDtoCatalogExporter;
use ApiSutra\OperationInventory\Catalog\Export\ResponseDtoCatalogWriter;

$writer = new ResponseDtoCatalogWriter([
    new MarkdownResponseDtoCatalogExporter(),
]);

$writer->writeTo('/path/dto-catalog.md', $mega->responseDtoCatalog());
```

### Кастомный label сервисов <a id="section-7"></a>
Если короткое имя класса (`RealtyClient`) не устраивает в заголовках, передайте
свой резолвер в exporter:

```php
use ApiSutra\Contracts\Interfaces\Inventory\ServiceLabelResolverInterface;

$resolver = new class implements ServiceLabelResolverInterface {
    public function resolve(string $serviceClass): string
    {
        return match ($serviceClass) {
            RealtyClient::class => 'Realty',
            TaxClient::class    => 'Tax',
            default             => $serviceClass,
        };
    }
};

$exporter = new MarkdownResponseDtoCatalogExporter($resolver);
```

В сами `ResponseDtoUsage` label не попадает (там хранится сырой FQCN) —
переименование чисто презентационное.

### Поведение под капотом <a id="section-8"></a>
- `MultiServiceResponseDtoCatalogFactory` собирает `operationInventory()` со
  всех сервис-клиентов и складывает их в `CompositeOperationInventory`
- `serviceClass` проставляется на каждый `ResponseDtoUsage` по карте
  `requestClass → serviceClass`, построенной за один проход по inventory
- если один `requestClass` встречается у нескольких сервисов, выигрывает первый
  по порядку `services()` (детерминированно)
- сервисы остаются изолированы: resource grouping не объединяется между
  сервисами, namespace-эвристика разводит `Reports` у `RealtyClient` и
  `Reports` у `TaxClient` по разным сервисам автоматически

## ResponseDtoCatalog <a id="section-9"></a>
Каталог response DTO — тонкий introspection-слой поверх `OperationInventory`,
отвечающий на вопрос «какие DTO реально возвращает SDK».

### Что входит в каталог <a id="section-10"></a>
- sync DTO из `#[Returns(...)]` или `#[Returns(type: ...)]`
- async-final DTO из `#[ContinuationResult(finalType: ...)]`
- download responses (`FileResponse`) из request-ов с `#[Download]`

Если у `Returns` задан `type`, то для каталога приоритет именно за `type`.
Если у request нет ни `Returns`, ни `ContinuationResult`, ни `#[Download]`,
он в каталог не попадает — но остаётся виден в обычном `OperationInventory`.

### Доступ <a id="section-11"></a>
На уровне клиента:
```php
$catalog = $client->responseDtoCatalog();
```

Или вручную:
```php
use ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;

$catalog = new ResponseDtoCatalog($client->operationInventory());
```

### API <a id="section-12"></a>
- `listAllDtoClasses(includeDownload = false): array<int, class-string>`
- `listSyncDtoClasses(): array<int, class-string>`
- `listAsyncFinalDtoClasses(): array<int, class-string>`
- `listDownloadResponseClasses(): array<int, class-string>`
- `usages(): array<class-string, array<int, ResponseDtoUsage>>`
- `usagesFor(class-string): array<int, ResponseDtoUsage>`
- `inventory(): OperationInventoryInterface`

`Download` намеренно не входит в `listAllDtoClasses()` по умолчанию, чтобы DTO и
`FileResponse` не смешивались. Включается явным `includeDownload: true` или через
`listDownloadResponseClasses()`.

### `ResponseDtoUsage` <a id="section-13"></a>
Каждая запись каталога — это `ResponseDtoUsage` со всеми полями, нужными exporter-ам:

- `requestClass`
- `resourcePath: array<int, string>|null`
- `resourceLabel: string|null`
- `httpMethod: HttpMethod|null`
- `endpoint: string|null`
- `title: string|null` / `description: string|null` (из `OperationDescriptor`)
- `responseClass: class-string` (sync DTO / async final DTO / `FileResponse`)
- `kind: ResponseDtoKind` — `Sync` / `AsyncFinal` / `Download`
- `pollRequest: class-string|null` (только для `AsyncFinal`)
- `unwrap: string|null` (`Returns::unwrap` или `ContinuationResult::unwrap`)

Все необходимые данные хранятся прямо в `ResponseDtoUsage` — exporter-у не нужно
делать join с `OperationInventory`.

### Resource grouping <a id="section-14"></a>
Resource — естественная top-level группа SDK, и в реальных провайдерах ресурсы
часто **вложены**:

```
\Resources\Reports\Tasks\Requests\CreateByFio\CreateByFioRequest
```

Здесь не один сегмент `Reports`, а путь `['Reports', 'Tasks']`.

Резолвер — `ResourceNameResolverInterface`:
- дефолтная реализация (`ResourceNameResolver`) использует path-эвристику между
  сегментами `\Resources\` и `\Requests\`
- провайдер может зарегистрировать свой resolver при сборке `OperationInventoryBuilder`
- если путь определить нельзя, оба поля (`resourcePath`, `resourceLabel`) будут `null`

`OperationInventory::all()` остаётся flat: каждый элемент хранит свой `resourcePath`
/ `resourceLabel`, иерархия — это уже вид (рендер), а не данные.

### Экспорт в файл <a id="section-15"></a>
Каталог сам про форматирование ничего не знает. Экспорт инкапсулирован отдельным
слоем по принципу strategy/adapter — новые форматы добавляются без правки самого
каталога.

Контракт:
```php
interface ResponseDtoCatalogExporterInterface
{
    public function format(): string;        // 'md', 'json', 'openapi', ...
    public function export(ResponseDtoCatalog $catalog): string;
}
```

Built-in: `MarkdownResponseDtoCatalogExporter` (format: `md`).

Запись:
```php
use ApiSutra\OperationInventory\Catalog\Export\MarkdownResponseDtoCatalogExporter;
use ApiSutra\OperationInventory\Catalog\Export\ResponseDtoCatalogWriter;

$writer = new ResponseDtoCatalogWriter([
    new MarkdownResponseDtoCatalogExporter(),
]);

$writer->writeTo('/path/dto-catalog.md', $client->responseDtoCatalog());
```

Поведение `writeTo`:
- если `format` не указан, он определяется по расширению пути
- если расширения нет и `format` не указан — `ConfigurationException`
- если для format нет зарегистрированного exporter-а — `ConfigurationException`

Кастомный формат от SDK:
```php
final readonly class JsonResponseDtoCatalogExporter implements ResponseDtoCatalogExporterInterface
{
    public function format(): string { return 'json'; }

    public function export(ResponseDtoCatalog $catalog): string
    {
        return json_encode([
            'all'   => $catalog->listAllDtoClasses(),
            'sync'  => $catalog->listSyncDtoClasses(),
            'async' => $catalog->listAsyncFinalDtoClasses(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

$writer = new ResponseDtoCatalogWriter([
    new MarkdownResponseDtoCatalogExporter(),
    new JsonResponseDtoCatalogExporter(),
]);

$writer->writeTo('/path/dto-catalog.json', $client->responseDtoCatalog());
```

### Async + Sync на одном request <a id="section-16"></a>
Если у запроса есть и `Returns(EnvelopeDto::class)`, и `ContinuationResult(finalType: FinalDto::class)`:

- `EnvelopeDto::class` идёт как `Sync`
- `FinalDto::class` идёт как `AsyncFinal`
- оба попадают в `listAllDtoClasses()`
- в `usages()` для каждого DTO — отдельный `ResponseDtoUsage`

### Поллинг-запрос <a id="section-17"></a>
Poll-запрос — это самостоятельный request-класс со своим `Returns` и попадает в
inventory обычным проходом. В каталоге он:

- виден через свои собственные `Returns`-DTO (как Sync usage)
- дополнительно указывается в `pollRequest` у `AsyncFinal`-usage стартового запроса

### Унифицированный контракт `ResponseDtoCatalogProviderInterface` <a id="section-18"></a>
Любой провайдер каталога — одиночный клиент или мегаклиент — реализует один
контракт:

```php
interface ResponseDtoCatalogProviderInterface
{
    public function responseDtoCatalog(): ResponseDtoCatalog;
}
```

Имплементируется:

- `AbstractClient` — single-client каталог (`serviceClass` у usages = `null`)
- мегаклиент через `ProvidesMultiServiceResponseDtoCatalogTrait` —
  multi-service каталог (`serviceClass` проставлен на каждый usage)

Это даёт **унифицированный полиморфный обход** разнородных провайдеров:

```php
/** @var array<int, ResponseDtoCatalogProviderInterface> $providers */
$providers = [$realtyClient, $kontur, $tax];

foreach ($providers as $provider) {
    foreach ($provider->responseDtoCatalog()->usages() as $dtoClass => $usages) {
        // ...
    }
}
```

Если нужен **один общий каталог** на произвольный набор провайдеров (а не
итерация по каталогам):

```php
$catalog = (new MultiServiceResponseDtoCatalogFactory())->merge(
    $realtyClient,   // одиночный AbstractClient
    $kontur,         // мегаклиент
    $tax,            // ещё мегаклиент
);
```

`merge()` сам разворачивает мегаклиенты в `services()` и проставляет
`serviceClass` для каждого usage.

### Multi-service usage (мегаклиент) <a id="section-19"></a>

Сборка через `MultiServiceResponseDtoCatalogFactory` и optional trait показана
в начале страницы; она использует те же правила фильтрации и экспорта.

[Каталоги провайдера](catalogs.md) · [Инвентаризация операций](operation-inventory.md).
