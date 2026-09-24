<!-- languages --> <a href="../../../en/guides/recipes/pagination.md">English</a> · <a href="pagination.md">Русский</a> <!-- /languages -->
# Пагинация <a id="section-1"></a>

Фрагменты предполагают SDK с ресурсом `users()`, пагинированной операцией `list()`
и его DTO. Правила выполнения описаны в [справочнике](../../reference/execution/pagination.md).

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Pagination\PaginationRule;
```

## Получить результат <a id="section-2"></a>

### send() на пагинированном запросе <a id="section-3"></a>
```php
$handle = $client->users()->list()->send();
$raw = $handle->raw();
```
Тип: `ResultHandle` → `ExecutionResult` (одна страница по умолчанию)

### Одна страница (default) <a id="section-4"></a>
```php
$resolved = $client->users()->list()->resolved();
```
Тип: `ResolvedResult<UserListDto>`

### Все страницы <a id="section-5"></a>
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::all())
    ->resolved();
```
Тип: `ResolvedResult<UserCollection>`

```php
$raw = $client->users()
    ->list()
    ->rules(PaginationRule::all())
    ->send()
    ->raw();
```
Тип: `PaginatedResult`

### Диапазон страниц <a id="section-6"></a>
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::range(2, 4))
    ->resolved();
```
Тип: `ResolvedResult<UserCollection>`

### Только N страниц <a id="section-7"></a>
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::pages(3))
    ->resolved();
```
Тип: `ResolvedResult<UserCollection>`

## Правило по умолчанию в ClientConfig <a id="section-8"></a>

### Клиент с правилом pages(2) <a id="section-9"></a>

`$transport` — транспорт, выбранный при [сборке клиента](../integration/standalone.md).
```php
$client = new MyClient(
    config: new ClientConfig(
        baseUrl: 'https://api.test',
        paginationRule: PaginationRule::pages(2),
    ),
    transport: $transport,
);

$resolved = $client->users()->list()->resolved();
```
Тип: `ResolvedResult<UserCollection>`

### Локальное переопределение правила <a id="section-10"></a>
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::range(3, 4))
    ->resolved();
```
Тип: `ResolvedResult<UserCollection>`

## Итератор страниц <a id="section-11"></a>
```php
foreach ($client->users()->list()->paginate()->withConcurrency(1) as $page) {
    if ($page->isFailed()) {
        break;
    }
    $items = $page->data;
}
```

## Пример конфигурации провайдера с пагинацией <a id="section-12"></a>
```php
use ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaResolverInterface;
use ApiSutra\Contracts\Interfaces\Pagination\PaginationItemsCollectionFactoryInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Metadata\PaginationMeta;
use ApiSutra\VO\Pipeline\PipelineContext;

final class ProviderPaginationMetaResolver implements PaginationMetaResolverInterface
{
    public function resolve(
        AbstractRequest $request,
        array $response,
        array $meta,
        PaginationConfig $config,
        ?PipelineContext $context
    ): PaginationMeta {
        return new PaginationMeta(
            total: isset($meta['count']) ? (int) $meta['count'] : null,
            currentPage: (int) ($meta['page'] ?? 1),
            perPage: (int) ($meta['rows'] ?? 0),
            hasMore: (bool) ($meta['has_more'] ?? false),
            nextCursor: null,
        );
    }
}

final class ProviderItemsCollectionFactory implements PaginationItemsCollectionFactoryInterface
{
    public function make(array $items): array|object
    {
        return new ProviderItemCollection($items);
    }
}

final class ProviderItemCollection
{
    public function __construct(
        private array $items,
    ) {}

    public function toArray(): array
    {
        return $this->items;
    }
}

$config = $config->with(
    paginationConfig: new PaginationConfig(
        metaPath: 'response',
        itemsPath: 'response.result',
        itemsType: ItemDto::class,
        itemsCollectionFactory: ProviderItemsCollectionFactory::class,
        metaResolver: ProviderPaginationMetaResolver::class,
    ),
    paginationRule: PaginationRule::all(),
);
```

## Загрузить независимые страницы конкурентно <a id="section-13"></a>

```php
$result = $client->users()->list()->paginate()->withPerPage(100)->withConcurrency(3)->all();
$selected = $client->users()->list()->paginate()->withConcurrency(3)->range(10, 20);
$promise = $client->users()->list()->rules(PaginationRule::all(concurrency: 3))->sendAsync();
$result = $promise->wait()->raw();
```
API должен поддерживать независимые page/offset; конкурентному all также нужны total/perPage.
Без total используйте последовательный all или явный диапазон. Сбор использует общий срок
и хранит все страницы; iterator выше остаётся последовательным.
[Полный контракт](../../reference/execution/pagination.md#section-21) · [Исполняемый mock-пример](../../../example/pagination/run.php).
