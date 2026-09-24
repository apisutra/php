<!-- languages --> <a href="pagination-items.md">English</a> · <a href="../../../ru/reference/execution/pagination-items.md">Русский</a> <!-- /languages -->
# Pagination elements and collections <a id="section-1"></a>

Use `Paginator::items(): Traversable` to consume elements as pages arrive. Creating
it sends no HTTP and materializes no results. Ordinary iteration still yields pages.
`$paginator` below is obtained from a request already bound to its client:

```php
foreach ($paginator as $page) {
    if ($page->isFailed()) {
        break;
    }
    // Inspect status, errors, HTTP response and metadata before reading data.
}

// A separate traversal of the same source, this time returning elements.
foreach ($paginator->items() as $item) {
    $repository->save($item); // The application's repository.
}
```

## Three methods named items <a id="contracts"></a>

| Method | Data and loading | Keys and errors |
| --- | --- | --- |
| PaginationItemsContainerInterface::items() | Elements of one materialized page: array or object | Original container keys; no execution-error delivery contract |
| PaginatedResult::items() | Already collected aggregate: array or object, no HTTP | Appended numeric keys and unique strings; accessor, not throw() |
| Paginator::items() | Lazy Traversable of elements; HTTP on consumption | Positions 0, 1, … across pages; FAILED throws, PARTIAL supplies data |

The stream preserves every value, including repeated IDs and repeated string keys
on different pages. It does not deduplicate. Use page results when original keys or
per-page diagnostics matter. A DTO stays the same object. The reader accepts arrays
and iterable collections, optionally inside PaginationItemsContainerInterface.
Null means no elements. Public object properties and toArray-only objects are not
collection iteration: a non-iterable collection gives `configuration_error`.

Calling DTO::toArray explicitly still serializes using its output rules. The SDK
never invokes collection toArray merely to enumerate pagination elements.

## Failure is not the end of the list <a id="errors"></a>

**A FAILED page throws through the configured result exception factory even with
throwOnErrors=false.** Otherwise a partial list could appear complete. For example,
a client configured with throwOnErrors=false and a second page that fails:

```php
// $paginator uses that client; $processed is application-owned progress.
$processed = 0;
try {
    foreach ($paginator->items() as $item) {
        $repository->save($item);
        ++$processed;
    }
} catch (Throwable $error) {
    // Earlier saves remain. Record an incomplete traversal and handle the error.
    $reporter->failed($processed, $error);
}
```

The factory is called once, regardless of throwOnErrors. A PARTIAL page supplies its
data like dataOrFail; absence of an exception does not prove absence of warnings or
errors. Use ordinary page iteration with throwOnErrors=false to inspect every status
and error yourself. Errors thrown by your loop body remain application exceptions.
The [runnable example](../../../example/dto-hydrator/run.php) exercises a failed second
page after delivering the first DTO.

## Lifetime and memory <a id="lifetime"></a>

Each items() traversal starts anew. The stream uses the existing page/cursor/offset
resolver and guards, retry, auth, quota and cooldown handling. It is sequential:
concurrency greater than one is rejected before HTTP. No prefetch is performed.
An empty page does not imply completion when metadata still indicates more pages.

The client totalTimeoutMs remains per page for lazy iteration. Use one external
withDeadline for the complete traversal. Collected all/pages/range have their own
[shared budget and concurrency contract](pagination.md#section-21).

The iterator does not accumulate earlier pages or elements. A page itself and data
retained by the application still occupy memory. Full consumption completes the
traversal; SDK extraction failures mark it failed. Releasing an unfinished generator
marks it abandoned and performs no further HTTP. A retained Generator after break
keeps its scope until the reference is released. Already executed requests and
application changes are not rolled back.

## Collecting terminals preserve values <a id="aggregation"></a>

all(), pages() and range() use the same reader but retain their collected-result
contract. Numeric elements append in page order; unique string keys survive.
A duplicate string key gives FAILED with code `hydration_error` and reason `pagination_item_key_collision`,
without partial aggregate data. Received page results remain available through pages().
Partial/IgnoreErrors cannot turn a local assembly failure into a successful overwrite.
The key itself is not included in diagnostics. Iterables that repeat a string key
within one page follow the same rule. Prefer a list when duplicates are meaningful.
This is a collision in the elements being combined, not an invalid DTO declaration;
the keys may originate from the provider or an application collection/factory.

For Laravel, [Pagination::collect](https://github.com/apisutra/laravel/blob/master/docs/en/reference/integrations/pagination.md)
wraps this stream in LazyCollection without introducing a second traversal mechanism.
