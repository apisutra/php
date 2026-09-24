<!-- languages --> <a href="diagnostics.md">English</a> · <a href="../../../ru/reference/dto/diagnostics.md">Русский</a> <!-- /languages -->
# Error paths and safe diagnostics <a id="section-1"></a>

Shared HydrationConfig or the DTO attributes also enable extended diagnostics,
without external rules. Discovering an attributed child preserves its parent's
path; after a cast/provider/hook, provenance remains Boundary or Unavailable.

## When SourcePath is enabled <a id="section-2"></a>

With explicit HydrationConfig or DTO declarations, hydration errors gain a JSON
Pointer `sourcePath`, `sourcePathKind`, and `sourceCandidates`. DTO `path` retains its
existing format. The exact path is available through `context()` and ExecutionResult;
automatic logs use `logContext()`, masking unknown keys. After custom transformation,
provenance is Boundary or Unavailable. See [values and examples](diagnostics.md#section-3).
Existing models without config or DTO declarations retain their error structure.

## Diagnostics and entry points <a id="section-3"></a>

When extended diagnostics are enabled, `HydrationException::context()` includes
`sourcePath`, `sourcePathKind`, and `sourceCandidates`. Existing `path` describes the
DTO; `sourcePath` is the original value's JSON Pointer (`~` → `~0`, `/` → `~1`, root
is an empty string). For example, an error at `rows[1].value.record_id` produces path
`items[1].id` and `sourcePath` `/rows/1/value/record_id`; unwrap=data adds that prefix.

| SourcePathKind | Meaning |
| --- | --- |
| Resolved | Exact location of the original value |
| Expected | Missing primary path; possible paths are in sourceCandidates |
| Boundary | Nearest known input to a cast/provider/hook/computed/aggregate |
| Unavailable | Original location unknown; sourcePath = null |

Automatic logging calls `logContext()`: declared segments and indices of real lists
are retained; unknown string **and numeric** dictionary keys become `*`. The exception
and ExecutionResult retain the exact path. The message does not contain response
values. After custom transformation, the core does not present a new path as the
original one. Ready without a declared continuation path has Unavailable provenance.

One client hydrator handles Returns (sync/promise), pagination, CompositeFlow, await,
and cached awaitAs. With a rule set, items-only pagination also applies itemsType;
without one it returns a raw result. The HTTP cache stores the response,
so another client hydrates a cache hit with its own rules. A non-null handler result,
RawResponse, and Download bypass DTO hydration. [Await readiness](../../guides/recipes/continuation.md)
is determined before strict validation of the final DTO.

Depth is limited to 512 active DTO/list nodes, counting the root as the first.
Sibling branches are not added together. Deeper input produces `hydration_depth_exceeded`;
a cyclic PHP object reference produces `cyclic_hydration_input`. The same object in
independent branches is allowed. Traversal state belongs to one root call and is not cached.

## Detailed hydration diagnostics <a id="section-4"></a>

For the documented built-in errors, the automatic ERROR log includes `reason`, `path`,
`expected`, `actual`, `httpStatus`, request class (`request`), and `traceId`. Actual
reports a type or missing/null; the message does not contain the original value.
For example, `items[2].price`, `expected` `float`, `actual` `array` identifies the violated
contract. For unknown discriminator variants, `Nested` Skip omits the item and KeepRaw retains its input.

If investigation requires the value itself, read the original response from the result:

```php
// $request is a configured SDK request with a declared DTO response.
$result = $request->send()->raw();
$error = $result->errors->first();
$diagnostic = $error?->context;           // Path, cause, and expected type.
$body = $result->response?->body;         // The original HTTP-response body without masking.
```

`response` and its body are available with `debug: false`: a hydration error does not
mask or reconstruct the response. The SDK requires no new configuration mode or
diagnostics store. A provider can explicitly save the needed response while handling
the result under its own access and masking rules. Raw body and `previous` are not
sanitized exports; do not send them wholesale to a shared automatic log. Safe message
guarantees cover the documented built-in checks, not arbitrary text from custom casts/constructors.

Adding a DTO path or source location creates an independent diagnostic copy. The final
exception contains the complete path; its `previous` chain preserves the original
hydration error, its trace, and any underlying cause. Intermediate diagnostic copies
are not retained for every nesting level, including when messages are localized.

Missing/null/type, date, and Nested Error failures produce
`HydrationException` / `hydration_error`. Malformed JSON in JsonCast raises an error.
[Presence and default rules](defaults.md#section-2) apply without additional settings.

`constructor_value_mismatch` means input differs from the constructor's fixed value.
[constructorValue checks](constructor-values.md) point to the whole property; the
diagnostics omit values and internal array keys.
