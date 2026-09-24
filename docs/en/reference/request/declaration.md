<!-- languages --> <a href="declaration.md">English</a> · <a href="../../../ru/reference/request/declaration.md">Русский</a> <!-- /languages -->
# Request declaration <a id="section-1"></a>

A short guide to creating requests, configuring execution options, and sending them.

## Basic structure <a id="section-2"></a>
```php
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/users')]
#[Returns(UserDto::class, unwrap: 'data')]
final class GetUser extends AbstractRequest
{
    public function __construct(
        #[Query('id')] public int $id,
    ) {}
}
```

## Data sources <a id="section-3"></a>
- `#[Query]`: URL parameters.
- `#[Body]`: request body.
- `#[Path]`: path placeholders.
- `#[Header]`: headers.
- `#[File]`: files.

Full list: [request attributes](../attributes/request.md).

## Body DTOs for repeated payloads <a id="section-4"></a>
If several requests use the same body, extract it into a DTO and use it as a request property.

```php
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Http\Put;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class UserPayload extends AbstractDto
{
    public function __construct(
        public string $email,
        public string $name,
    ) {}
}

#[Post('/users')]
final class CreateUser extends AbstractRequest
{
    public function __construct(
        #[Body('user')]
        public UserPayload $payload,
    ) {}
}

#[Put('/users/{id}')]
final class UpdateUser extends AbstractRequest
{
    public function __construct(
        public int $id,
        #[Body('user')]
        public UserPayload $payload,
    ) {}
}
```

The DTO is serialized through `#[To]`, casts, and the naming strategy.
`#[Body]` specifies a nested path when needed.

## Many body fields without boilerplate <a id="section-5"></a>
For a request class with many payload fields, use class-level `RequestDefaults`
and keep property attributes only where an override is needed:

```php
use ApiSutra\Attributes\Request\RequestDefaults;
use ApiSutra\Enums\Request\RequestUnmappedTarget;

#[RequestDefaults(unmapped: RequestUnmappedTarget::Body)]
final class PatchSomethingRequest extends AbstractRequest {}
```

This is the recommended approach for verbose `POST/PUT/PATCH` requests.
Full contract and priorities: [request attributes](../attributes/request.md#section-11).

## Root body for JSON Patch / bulk <a id="section-6"></a>
If the endpoint expects a body shaped as `[...]` rather than `{...}`, use `BodyRoot`:

```php
use ApiSutra\Attributes\Request\BodyRoot;

#[BodyRoot]
public array $operations;
```

The SDK sends `operations` as the root payload.
Details and restrictions: [request attributes](../attributes/request.md#section-6).

## OneOf and discriminator for polymorphic bodies <a id="section-7"></a>
Use class-level `RequestOneOf` and `RequestDiscriminator` for polymorphic payloads.
The contract is checked before transport, with diagnostics in the standard error format.

```php
use ApiSutra\Attributes\Request\RequestDiscriminator;
use ApiSutra\Attributes\Request\RequestOneOf;
use ApiSutra\Enums\Request\OneOfMode;

#[RequestOneOf(
    name: 'signature_payload',
    variants: [
        'cloudcrypt' => ['certificateId'],
        'goskey' => ['goskeyData'],
    ],
    requiredCommon: ['type', 'contents'],
    mode: OneOfMode::ExactlyOne,
)]
#[RequestDiscriminator(
    field: 'type',
    map: [
        'CERT_PROVIDER' => 'cloudcrypt',
        'HSM_PROVIDER' => 'goskey',
    ],
)]
final class CreateSignatureRequest extends AbstractRequest {}
```

Nested contracts can use dot paths:
```php
#[RequestOneOf(
    name: 'signature_payload',
    variants: [
        'cloudcrypt' => ['signature.certificateId'],
        'goskey' => ['signature.goskey.data'],
    ],
    requiredCommon: ['type', 'signature.contents'],
    mode: OneOfMode::AtLeastOne,
)]
```

Details: [RequestOneOf](../attributes/request.md#section-13),
[RequestDiscriminator](../attributes/request.md#section-14).

## Serialization <a id="section-8"></a>
Default mapping rules and priorities are documented separately:
[request serialization](../serialization/README.md).

## Request behavior <a id="section-9"></a>
Use behavior attributes for cache/retry/timeout/rate-limit settings.
Full list: [behavior attributes](../attributes/behavior.md).

## Caching <a id="section-10"></a>
Manage the cache with:
- `withCache()` / `withoutCache()`.
- `withCacheScope(string $scope)`: an additional execution cache label within automatic identity/tenant boundaries.
- `clearCache()`: invalidates request variants within its namespace.

Details: [HTTP cache](../execution/cache.md).

## Authentication <a id="section-11"></a>
- `#[AuthScope]`: selects the request's scope.
- `#[NoAuth]`: disables auth.
- Runtime overrides: `withAuth()`, `withoutAuth()`, `withAuthScope()`, `forceAuthScope()`.

Usually `#[AuthScope]` or the default `auth` in `ClientConfig` is sufficient.
Use `forceAuthScope()` only when you need to override `#[NoAuth]`.

## Provider credentials enrichment <a id="section-12"></a>
If the provider requires service credentials in `body/query/multipart form`,
configure them centrally through `ClientConfig::credentialsConfig` instead of
repeating fields in every request class.

The default merge mode is `fill-missing`:
- Explicit request fields are not overwritten.
- Provider defaults fill only missing keys.

For specific exceptions:
- `#[SkipCredentialsEnrichment]` disables enrichment for a particular request.
- Runtime overrides are `withCredentialsEnrichment()` / `withoutCredentialsEnrichment()`,
  `withCredentialsMergeMode(...)`, and `withCredentialsScope(...)`.

```php
use ApiSutra\Attributes\Request\SkipCredentialsEnrichment;
use ApiSutra\Enums\Request\CredentialsMergeMode;

$result = $request
    ->withCredentialsScope('system')
    ->withCredentialsMergeMode(CredentialsMergeMode::Overwrite)
    ->send();
```

## Pagination <a id="section-13"></a>
For requests supporting pagination:
```php
$result = $request->paginate()->all();
```
Modes are set through `PaginationRule` (single/all/pages/range) and can be overridden:
```php
use ApiSutra\Pagination\PaginationRule;

$result = $request->rules(PaginationRule::pages(2))->send()->raw();
```
Details: [behavior attributes](../attributes/behavior.md) and [pagination](../execution/pagination.md).

## Common runtime options <a id="section-14"></a>
```php
$result = $request
    ->withCache(60)
    ->withTimeout(5)
    ->withHeader('X-Trace-Id', $traceId)
    ->send();
```
More examples: `withRetry()`, `withoutCache()`, `withRateLimit()`, `withDelay()`, `withTraceId()`.

`withDeadline($deadline)` / `withoutDeadline()` apply or remove a
[shared external deadline](../execution/deadlines.md#section-4).
`withRawResponse()` selects the [undecoded response string](../attributes/response.md#section-7).
All these options apply to a new execution without mutating the original request.

`withRetry(attempts)` preserves other retry settings and does not confirm POST/PATCH
safety. Safety is determined by client configuration, optional `#[Retry(safe: true/false)]`,
and an optional RetrySafetyPolicyInterface; omitted safe and null are equivalent.
See [retry safety](../execution/retry.md#section-6).
For credentials enrichment, see the section above.

## Sending and results <a id="section-15"></a>
```php
$handle = $request->send();          // Synchronous execution.
$promise = $request->sendAsync();    // ResultPromiseInterface<ResultHandle>.
$asyncHandle = $promise->wait();     // Completed ResultHandle.
$raw = $asyncHandle->raw();          // ExecutionResult.
$data = $asyncHandle->dataOrFail();  // Data or exception.
$resolved = $request->resolvedAsync(); // New execution; a ResolvedResultInterface promise.
```

sendAsync returns a [typed promise](../results/promises.md).
The completed handle after wait exposes ordinary synchronous reading methods.

Async pagination awaits each dependent page before requesting the next, while
unrelated executions can progress during HTTP and SDK waits. The aggregate promise
resolves after traversal. Public throwOnErrors failures reject the promise; wait() awaits delivery. See the [async contract](../execution/transport.md).

## Full URL for one execution <a id="section-16"></a>

`withUrl($url)` sets a complete absolute address; `withoutUrl()` clears the override.
The original request/config is unchanged. The base path/query is not appended;
auth, shared request enrichers, and caching are not automatically enabled.
For relative endpoints, use `withBaseUrl()`, which includes credential isolation
when the origin changes.

Full contract, download example: [external URLs](../serialization/uri-query.md).

## Download destination <a id="section-17"></a>

`#[Download]` supports `withDownloadTo(string|StreamInterface $target, bool $overwrite = false)`
and `withoutDownloadTo()`. Existing paths are protected from replacement by default;
the SDK does not close user streams. Without a target, it automatically uses a temporary file.
See [files](../../guides/recipes/files.md) for saving and ownership contracts.

`withCache(10)->withoutCache()->withCache()` re-enables caching with TTL 10:
null here means “do not set a new TTL”. `withoutRateLimit()` disables the limiter
even if the previous override object remains inside the options. A new execution
copy inherits the original request options; for an entirely new set, explicitly
use `RequestOptions::empty()` through `RequestExecution`, rather than assuming
all settings reset implicitly.

## RequestContractViolation diagnostics <a id="section-18"></a>
A violated class-level request contract (`RequestOneOf`/`RequestDiscriminator`)
returns `ErrorCode::RequestContractViolation` before any HTTP call.

`RequestError.context` keys:
- `contract`: the oneOf contract name.
- `discriminatorField`: the discriminator field.
- `discriminatorValue`: the current discriminator value.
- `matchedVariant`: the variant actually selected/resolved.
- `filledVariants`: the list of variants actually populated.
- `violations`: machine-readable reasons for the contract violation.

Typical `violations.code` values:
- `none_selected`, `multiple_selected`.
- `required_common_missing`, `unknown_variant_field`.
- `unknown_discriminator_field`, `unknown_discriminator_value`.
- `discriminator_mismatch`, `prohibited_variant_fields`.
- `invalid_dot_path_root` (when a dot path points into a scalar root).

For successful oneOf requests, `requestDebug()` also provides a `oneOf` block
with brief diagnostics for the selected variant.
