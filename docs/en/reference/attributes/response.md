<!-- languages --> <a href="response.md">English</a> · <a href="../../../ru/reference/attributes/response.md">Русский</a> <!-- /languages -->
# Response attributes <a id="section-1"></a>

## Signatures and targets <a id="section-2"></a>

Class names belong to `ApiSutra\Attributes\Response`.
Signatures show constructor parameters and defaults; target identifies where the
attribute is allowed. Behavior and priorities are covered in the topic links below.

| Attribute | Target | Constructor |
| --- | --- | --- |
| ContinuationResult | CLASS | `ContinuationResult(string $finalType, ?string $unwrap = null, ?string $pollRequest = null, ?ContinuationMode $defaultMode = null, ?string $stateResolver = null)` |
| Download | CLASS | `Download()` |
| RawResponse | CLASS | `RawResponse()` |
| Returns | CLASS | `Returns(string $response, ?string $unwrap = null, ?string $type = null, ?string $mismatchMessage = null, string\|false\|null $hydrator = null)` |

These attributes describe response types and file download mode.

## When to use them <a id="section-3"></a>
- **Returns** — a DTO response or unwrapping nested data.
- **Download** — a file response.
- **RawResponse** — body text without decoding.
- **ContinuationResult** — final readiness and type differ from the initial response.

## Returns <a id="section-4"></a>
**Target:** class.
**Parameters:**
- `response: string` — result DTO class.
- `unwrap?: string` — data path inside the response.
- `type?: string` — DTO class override after unwrapping.
- `mismatchMessage?: string` — message for a final type mismatch

The final successful type is checked automatically, including values returned by
extension handlers and stages. Subclasses are accepted; other types produce
`hydration_error/response_type_mismatch`. [Messages, factories, and boundaries](../results/exceptions.md).

type is useful when different requests use the same `unwrap` but need different DTOs,
such as different models in one container. Without type, response is used.

The client hydrator applies connected `ClientConfig::hydration`, including to plain
classes. [External rules and diagnostics](../../guides/dto/plain-models.md) work the
same for synchronous results and promises.

Example:
```php
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Returns(UserDto::class, unwrap: 'data.user')]
final class GetUser extends AbstractRequest {}
```

### Declaration validation <a id="declaration-validation"></a>

Before HTTP and request hooks, the SDK checks that all declared DTO classes exist
(response, optional type, and the effective getResponseType()) and that a declared
hydrator implements DtoHydratorInterface. Invalid declarations give configuration_error
without sending the request. DTO constructors are not invoked or required to be public;
native field rules and hydration remain separate checks.

Declaration checks precede request-data validation, including validateCustom().
If both the declaration and input data are invalid, the result contains
configuration_error and no validationErrors: data validation has not run.
With a valid declaration, invalid input produces validation_failed as usual.
Neither case sends HTTP; the order is the same for send() and sendAsync().

An explicit Returns must be valid even when Download or a final response handler
does not use hydration. EarlyReturn does not skip this check; RawResponse with Returns
remains incompatible. Dynamic accessors are checked for the current request, without
caching a verdict by request class. The handler's dependencies are created only if
hydration is needed: a DI construction failure can still follow HTTP. No new option
or eager call to the hydrator's supports() is introduced.

### Strict unwrap <a id="section-5"></a>

An explicit `unwrap` is a required dot-notation path, including list indices (`data.0`).
It is checked after `BeforeHydrate`. If absent, the result contains `hydration_error`
with reason `unwrap_path_missing`; the HTTP response is retained. The document root
is not substituted for missing data.

A found null differs from an absent path. Returns declares a required DTO: null or
a scalar instead of its data produces `unexpected_response_shape`. An empty array
enters ordinary hydration and may be valid for a DTO with defaults. `unwrap: null`
means no extraction. The `unwrap` check needs no additional client settings.

If an absent object is valid, declare a wrapper DTO with a nullable field:

```php
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class OrderDto extends AbstractDto
{
    public function __construct(public string $id) {}
}

final readonly class ActiveOrderDto extends AbstractDto
{
    public function __construct(public ?OrderDto $order) {}
}

#[Returns(ActiveOrderDto::class, unwrap: 'data')]
final class GetActiveOrder extends AbstractRequest {}
```

For `{"data":{"order":null}}`, the result succeeds and `order === null`. This attribute
does not directly declare a nullable result DTO. Root null/204 without a DTO retains
[its contract](../results/handles.md#section-3). For a DTO without `unwrap`, existing
normalization of root null/204/empty body to [] remains.

Use a path matching the response, omit `unwrap`
for a root DTO, or explicitly normalize response variants through `BeforeHydrate` or
ResponseHandler. `ContinuationResult::unwrap` and pagination itemsPath have separate
rules; this strict contract applies to Returns.

## ContinuationResult <a id="section-6"></a>

**Target:** class. `finalType: string` is the required final DTO class;
`unwrap?: string` is the final data path and presence criterion;
`pollRequest?: string` is a request class with one required scalar token parameter;
`defaultMode?: ContinuationMode` is the provider mode;
`stateResolver?: string` is a `ContinuationStateResolverInterface` class without required
constructor arguments.

Criterion priority: attribute `stateResolver` → nonempty `unwrap` → client resolver.
Without a criterion, awaiting produces a configuration error before polling. Unlike
Returns, absent/null data at `unwrap` means Pending here. A Ready data error immediately
ends waiting and retains the full path and last HTTP response. See
[Provider Async Await](../../guides/recipes/continuation.md) for the full contract and a resolver example.

## RawResponse <a id="section-7"></a>

**Target:** class. No parameters. Optional; no ClientConfig settings are needed.

```php
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\RawResponse;
use ApiSutra\Core\AbstractRequest;

#[Get('/export')]
#[RawResponse]
final class ExportRequest extends AbstractRequest
{
}

$result = $client->send(new ExportRequest())->raw();
$text = $result->data;
```

For one execution: `$request->withRawResponse()->send()`. Priority: runtime → attribute
→ standard Auto. `withRawResponse(false)` selects Auto; `withRawResponse(null)` removes
the override and restores attribute inheritance. The chain does not change the original request.

Raw returns the transport-provided body without JSON decoding or response format
handlers: `'null'` remains a string; an empty body and 204 produce ''. This is useful
for incorrect Content-Type or intentionally reading JSON as text. In Auto, an unknown
explicit non-JSON format without a DTO already returns a string; see the
[response contract](../results/handles.md#section-3).

Raw is incompatible with Returns/response DTOs, pagination, and download: the effective
combination is rejected as `configuration_error` before HTTP. Use Download for files.
`BeforeHydrate` is skipped for a string; other hooks remain. An HTTP error does not
become success: for example, 429 remains an error regardless of Raw.

For manual diagnostics, the original body is already in `$result->response?->body`,
even after decoding errors in result-first mode. `send()->raw()` obtains the entire
ExecutionResult; it does not enable RawResponse. With `throwOnErrors`, an exception
may interrupt result retrieval. The HTTP cache stores the original response: Auto
and Raw use the same key and need no prefixes or separate cache.

## Download <a id="section-8"></a>
**Target:** class.
**Parameters:** none.
**Effect:** marks the request as a file download; its result is FileResponse.

The body streams to a temporary file without extra settings. `withDownloadTo()` sets
a local path or writable stream for the final result. See the
[file guide](../../guides/recipes/files.md) for ownership, caching, and compatibility.

Returns::hydrator selects a [custom DTO hydrator](../dto/hydrators.md): null inherits the client, false selects native hydration, and a class name is resolved at execution.
