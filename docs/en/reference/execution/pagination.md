<!-- languages --> <a href="pagination.md">English</a> · <a href="../../../ru/reference/execution/pagination.md">Русский</a> <!-- /languages -->
# Pagination <a id="section-1"></a>

Default pagination rules are configured in `ClientConfig::paginationRule`.

## Basic configuration <a id="section-2"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Pagination\PaginationRule;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    paginationRule: PaginationRule::all(),
);
```

The default is `paginationRule = PaginationRule::single()`: pagination is not enabled
until you set a rule or call `paginate()`.

## Request overrides <a id="section-3"></a>
- `#[Pagination]`: pagination configuration (paths and fields).
- Runtime override: `rules(PaginationRule::pages(2))`.

## PaginationConfig details <a id="section-4"></a>
```php
use ApiSutra\Config\PaginationConfig;

$config = $config->with(paginationConfig: new PaginationConfig(
    pageParam: 'page',
    limitParam: 'per_page',
    cursorParam: 'cursor',
    metaPath: 'meta',
    itemsPath: 'data.items',
    offsetBased: false,
    maxPages: 500,
));
```

`PaginationConfig` defaults when not supplied:
- `pageParam = page`, `limitParam = limit`, `cursorParam = null`.
- `metaPath = meta`, `itemsPath = data`.
- `offsetBased = false`, `maxPages = 1000`.

Key fields:
- `itemsType`: the collection item type.
- `itemsCollection`: the items collection class.
- `itemsCollectionFactory`: the items collection factory.
- `metaResolver`: a custom metadata resolver.

Recommendations:
- For nonstandard metadata fields, implement a separate `PaginationMetaResolverInterface` class.
- For typed items, use `itemsCollection` or `itemsCollectionFactory`.
- To return a metadata + items wrapper, use a DTO container based on `AbstractPaginationContainerDto`.

Use `itemsCollection` when the collection can be created through `fromArray()`/`make()`/`__construct`.
Use `itemsCollectionFactory` for complex initialization (dependencies, validation).

The practical [provider configuration](../../guides/recipes/pagination.md#section-12)
shows these parameters in use.

## Offset-based pagination <a id="section-5"></a>
With `offsetBased = true`, `page` becomes an offset.
`limit` is required; otherwise an exception is thrown.

Pagination is available for requests implementing `PaginableInterface`,
usually through `AbstractPaginatedRequest`.

## Basic structure <a id="section-6"></a>
```php
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/users')]
final class ListUsers extends AbstractPaginatedRequest {}
```

## Execution <a id="section-7"></a>
```php
$result = (new ListUsers())->paginate()->all();
$result = (new ListUsers())->paginate()->pages(3);
$result = (new ListUsers())->paginate()->range(2, 5);
```

## Configuring the pagination schema <a id="section-8"></a>
```php
use ApiSutra\Attributes\Behavior\Pagination;

#[Pagination(
    pageParam: 'page',
    limitParam: 'per_page',
    itemsPath: 'data.items',
    metaPath: 'meta',
    offsetBased: true,
    cursorParam: 'cursor',
    maxPages: 100,
)]
final class ListUsers extends AbstractPaginatedRequest {}
```

Additional fields:
- `itemsType`: the collection item type.
- `itemsCollection`: the items collection class.
- `itemsCollectionFactory`: the items collection factory.

## Execution rules (PaginationRule) <a id="section-9"></a>
`single()`, `all(failStrategy, concurrency)`, `pages(count, failStrategy, concurrency)` and
`range(from, to, failStrategy, concurrency)` are immutable rules. Defaults: FailAll and concurrency=1.
The schema attribute/config describes the provider; execution policy belongs to the rule.

## PaginationMeta and resolution <a id="section-10"></a>
Metadata extraction priority:
1) `PaginationMetaOverrideInterface` on the request.
2) `PaginationConfig::metaResolver` (class or instance).
3) `DefaultPaginationMetaResolver`.

`PaginationMeta` contains `total`, `currentPage`, `perPage`, `hasMore`, and `nextCursor`.

By default, `DefaultPaginationMetaResolver` looks for:
- `total` / `count`.
- `page` / `currentPage` / `current_page`.
- `per_page` / `perPage` / `limit` / `page_size`.
- `next_cursor` / `nextCursor`.
- `has_more` / `hasMore`.

If the response omits currentPage, the standard resolver uses the execution’s requested
page; in offset mode it derives the page from offset/limit. Explicit response metadata
keeps priority. Without sufficient runtime parameters, the fallback remains page 1.
Use a custom resolver if metadata uses other fields or needs additional logic.

### Custom metadata resolver <a id="section-11"></a>
For nonstandard metadata fields, implement `PaginationMetaResolverInterface`:
```php
use ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaResolverInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\VO\Metadata\PaginationMeta;
use ApiSutra\VO\Pipeline\PipelineContext;

final class CustomPaginationMetaResolver implements PaginationMetaResolverInterface
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

#[Pagination(metaResolver: CustomPaginationMetaResolver::class)]
final class ListUsers extends AbstractPaginatedRequest {}
```

### Item typing and collections <a id="section-12"></a>
For a typed set with a custom collection:
Use `itemsCollection` for classes with `fromArray()`, `make()`, or a constructor accepting items.
`itemsCollectionFactory` implements `PaginationItemsCollectionFactoryInterface::make(array): array|object`
and takes precedence. Without either, the aggregate contains an array.
The [provider recipe](../../guides/recipes/pagination.md#section-12) shows a complete factory.

## Metadata + items container <a id="section-13"></a>
To retain metadata in a DTO wrapper, use:
- `PaginationItemsContainerInterface`.
- Or `AbstractPaginationContainerDto`.

The paginator then extracts items through `items()` and can insert them back.

### DTO container for a paginated response <a id="section-14"></a>
Declare `#[Returns(PagedUsersDto::class)]` on the request. The DTO extends
`AbstractPaginationContainerDto`, passes `items` to the parent constructor and implements
`withItems(array|object $items): static`, returning a new DTO with unchanged metadata.
Each page keeps that DTO; the final aggregate extracts its items.

## PaginatedResult <a id="section-15"></a>
The pagination result provides:
- `items()`: all items.
- `pages()`: a collection of per-page results.
- `meta()`: `PaginationMeta`.

## Guards and limits <a id="section-16"></a>
- `PaginationConfig::maxPages`: a hard page limit.
- The guard checks page/cursor progress to stop infinite loops.

## Errors and protective stops <a id="section-17"></a>

A page error preserves the original SDK code, HTTP response, and context.page.
If the first page returned 20 objects and the next returned 403, the aggregate
contains PARTIAL, those 20 objects, and `forbidden` with the real 403.

FailAll stops traversal. Partial may continue page/offset pagination, but cursor
pagination stops after an error because no next cursor exists. Repeating any already
visited cursor stops traversal before another send. The value `"0"` is preserved.
`maxPages=null` removes the count limit while retaining cycle protection; the standard
maxPages remains 1000. Opaque cursors do not appear in error context.

A protective stop reports `execution_error` with `pagination_stalled` or
`pagination_max_pages_reached`. After successful pages, with throwOnErrors false the iterator yields one
additional FAILED result and ends; with true it throws instead; this is not an HTTP call. Check `isFailed()`
before reading page data. The iterator does not yield the original failed page again.
`pages(N)` and `range()` retain ordinary selection limits without an additional error.

## External DTO rules <a id="section-18"></a>

Items-only pagination without Returns hydrates `itemsType` only when
`ClientConfig::hydration` is explicitly non-null, including an empty `new HydrationConfig()`.
Without the block (`hydration: null`), it returns the previous raw arrays: even Extras,
RequiredInput, or Shape on the item model do not enable constructors and checks.
This is intentional: attributes apply on entry into a DTO, and the raw path does
not enter one. `$config->with(hydration: null)` returns a copy using raw behavior;
the original client retains its behavior. A Returns container still types items
without config; collections/factories apply on the existing typed paths.
Without `itemsType`, no new type is inferred.

[Hydration configuration](../dto/configuration.md) · [Rules](../dto/field-rules.md).

## Traversal diagnostics <a id="section-19"></a>

Each traversal has its own root; pages link to it through `trace.parentExecutionId`.
`PaginatedResult::traceId` belongs to that root even without an explicit override.
An iterator starts execution when consumed; releasing an unfinished generator
records `abandoned` without requesting another page. A new traversal gets a new ID.
The aggregate has no HTTP request snapshot: inspect `$result->nested` page results.
With debug enabled, `$page->requestDebug()` masks secrets and `$page->debug?->duration`
measures that page’s execution. Root elapsed time is in the terminal audit event’s duration.
Pages stay in logical order; audit/log events retain execution order. Trace and audit
remain available without debug; errors of the traversal carry its root traceId.
[Lifecycle and event fields](../results/observability.md#section-6).

## Executor and final delivery <a id="section-20"></a>

Every page uses the client's executor with a Single override, preserving other runtime
options. The original request is unchanged even when the client defaults to all/pages.
A direct paginator can receive a client explicitly:
`new Paginator($request, client: $client)`; its full constructor accepts request,
options, paginationOptions, client, in that order.

Direct and client-driven traversal follow the [final status policy](../results/errors.md#section-2):
FAILED returns with throwOnErrors false and throws with true; PARTIAL returns in both
modes. Guards follow this rule too. The factory receives one complete aggregate per
automatic delivery. During iteration, a failed page is yielded with false or thrown
with true, then traversal stops; a guard is delivered similarly. Both collecting entry points use one canonical executor scope, metadata extraction and budget.
Only the lazy iterator owns a separate traversal scope.

## Concurrent page loading <a id="section-21"></a>

For independent page/offset APIs, using the client bound to `ListUsers`:
```php
$result = (new ListUsers())->paginate()->withPerPage(100)->withConcurrency(3)->all();
$range = (new ListUsers())->paginate()->withConcurrency(3)->range(10, 20);
$promise = (new ListUsers())->rules(PaginationRule::all(concurrency: 3))->sendAsync();
$result = $promise->wait()->raw();
```
`send()` and paginator terminals remain synchronous. Concurrent HTTP requires a
concurrent transport (the standard Guzzle transport supports it); sync-only PSR-18
returns configuration_error before bootstrap auth/HTTP. No workers or new setup.

The first selected page is requested once as bootstrap; range starts at `from`, not 1.
Then free slots accept new pages, up to concurrency. **Concurrent all requires total
and positive perPage when bootstrap hasMore=true.** Otherwise choose sequential all
or explicitly bounded pages/range; these do not discover an unknown end. A naturally
last bootstrap needs no total. There is no speculative window or short-page heuristic.
Known cursors are rejected before HTTP; a cursor discovered in bootstrap rejects
before the remaining pages. Cursor traversal remains sequential.

The all upper bound is frozen after bootstrap and may shrink, never grow. A known
perPage must match the requested size and stay stable. Without a requested limit,
bootstrap's positive perPage is applied to later pages. A bounded page API without
size metadata must keep its default size stable. Conflicting page/size metadata gives
`pagination_metadata_changed`. Offset mode requires an explicit positive limit.

Items, nested pages and page errors are ordered by requested page; meta comes from
the highest successful page. Events retain real completion order. FailAll stops new
assignments and drains started pages; Partial/IgnoreErrors continue independent pages
and retain errors. Early end/shrinking total also stops only new assignments: started
responses remain, and there is no fixed bound on extra requests during a changing dataset.
maxPages counts issued requests, including bootstrap. A guard gives PARTIAL with items,
FAILED without items. No errors means SUCCESS, including an empty dataset.
All collecting terminals retain all results/items: active work is bounded by concurrency,
not total memory. A pool of N traversals with concurrency M may run N×M page requests.
Provider snapshot consistency and checkpoints remain application responsibilities.

## Builder snapshots and precedence <a id="section-22"></a>

`withPerPage`, `withFailStrategy`, `withConcurrency` return copies; keep the returned value.
Policy precedence: builder override → runtime rule → client rule. Rules replace the whole
policy; explicit concurrency=1 overrides a client default of 5. The terminal chooses its
mode/range without changing other builders. `pages(N)` means N pages from runtime start
(default 1); `range(from,to)` is inclusive. Nonpositive sizes/counts/concurrency, invalid
ranges and arithmetic overflow are configuration errors, not empty success.
The lazy `foreach ($request->paginate()->withConcurrency(1) as $page)` stays sequential;
effective concurrency>1 is rejected before HTTP. It does not prefetch or collect pages.

## Traversal deadline and cancellation <a id="section-23"></a>

Collecting all/pages/range share one budget, including bootstrap, auth, quotas, cooldown,
retry waits and result assembly. It is the minimum of totalTimeoutMs, an external
withDeadline and any parent budget. **Three sequential 400 ms pages with totalTimeoutMs=1000
cannot complete successfully in 1200 ms**: the third times out, preserving the first two pages
as PARTIAL. Concurrent elapsed time is wall duration, not a sum of page durations.
The lazy iterator retains a fresh client budget per page; use the same withDeadline
on the request for a whole-iterator deadline. Shared trace alone does not share time.
Deadline expiry stops new assignments and preserves received data with
`timeout / execution_deadline_exceeded / stage`. Checks cannot interrupt arbitrary PHP callbacks.
Async cancellation/abandonment uses the ordinary promise contract: active tasks are
cancelled, cleanup runs, and no final aggregate is promised. Cancellation is not server rollback.
See the [runnable example](../../../example/pagination/run.php).

See [lazy items and collection contracts](pagination-items.md): `Paginator::items()` streams values, preserves DTOs and throws on FAILED even with throwOnErrors=false. Collecting terminals reject duplicate string keys instead of overwriting.
