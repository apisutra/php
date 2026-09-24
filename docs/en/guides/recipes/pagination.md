<!-- languages --> <a href="pagination.md">English</a> · <a href="../../../ru/guides/recipes/pagination.md">Русский</a> <!-- /languages -->
# Pagination <a id="section-1"></a>

These snippets assume an SDK with a `users()` resource, a paginated `list()` operation,
and its DTOs. Execution rules are in the [reference](../../reference/execution/pagination.md).

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Pagination\PaginationRule;
```

## Get the result <a id="section-2"></a>

### send() on a paginated request <a id="section-3"></a>
```php
$handle = $client->users()->list()->send();
$raw = $handle->raw();
```
Type: `ResultHandle` → `ExecutionResult` (one page by default)

### One page (default) <a id="section-4"></a>
```php
$resolved = $client->users()->list()->resolved();
```
Type: `ResolvedResult<UserListDto>`

### All pages <a id="section-5"></a>
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::all())
    ->resolved();
```
Type: `ResolvedResult<UserCollection>`

```php
$raw = $client->users()
    ->list()
    ->rules(PaginationRule::all())
    ->send()
    ->raw();
```
Type: `PaginatedResult`

### Page range <a id="section-6"></a>
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::range(2, 4))
    ->resolved();
```
Type: `ResolvedResult<UserCollection>`

### Only N pages <a id="section-7"></a>
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::pages(3))
    ->resolved();
```
Type: `ResolvedResult<UserCollection>`

## Default rule in ClientConfig <a id="section-8"></a>

### Client with the pages(2) rule <a id="section-9"></a>

`$transport` is the transport selected during [client assembly](../integration/standalone.md).
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
Type: `ResolvedResult<UserCollection>`

### Local rule override <a id="section-10"></a>
```php
$resolved = $client->users()
    ->list()
    ->rules(PaginationRule::range(3, 4))
    ->resolved();
```
Type: `ResolvedResult<UserCollection>`

## Page iterator <a id="section-11"></a>
```php
foreach ($client->users()->list()->paginate()->withConcurrency(1) as $page) {
    if ($page->isFailed()) {
        break;
    }
    $items = $page->data;
}
```

## Provider pagination configuration example <a id="section-12"></a>
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

## Load independent pages concurrently <a id="section-13"></a>

```php
$result = $client->users()->list()->paginate()->withPerPage(100)->withConcurrency(3)->all();
$selected = $client->users()->list()->paginate()->withConcurrency(3)->range(10, 20);
$promise = $client->users()->list()->rules(PaginationRule::all(concurrency: 3))->sendAsync();
$result = $promise->wait()->raw();
```
The API must expose independent page/offset requests; concurrent all also needs total/perPage.
For unknown totals use sequential all or an explicit range. Collected results use one deadline
and retain all pages in memory; the iterator above remains sequential.
[Complete contract](../../reference/execution/pagination.md#section-21) · [Runnable mock example](../../../example/pagination/run.php).
