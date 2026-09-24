<!-- languages --> <a href="handles.md">English</a> · <a href="../../../ru/reference/results/handles.md">Русский</a> <!-- /languages -->
# ResultHandle and result representations <a id="section-1"></a>

`send()` returns `ResultHandle`. Its `raw()` yields `ExecutionResult`; `resolved()`
provides an application-level result view. `dataOrFail()` extracts data or throws
when the status is `FAILED`; `PARTIAL` returns data without throwing. The [example SDK](../../../example/sdk/run.php) runs both scenarios.

Active `Returns` guarantees the final successful value’s type. Per-operation messages
and custom client exceptions have a [separate contract](exceptions.md).

## Choosing a result level <a id="section-2"></a>

| API | Result and purpose |
| --- | --- |
| `request->send()` / `client->send($request)` | ResultHandle for subsequent reading |
| `request->resolved()` / `handle->resolved()` | ResolvedResultInterface: data, status, and errors |
| `request->dataOrFail()` / `handle->dataOrFail()` | Data, or an exception on FAILED |
| `handle->raw()` | ExecutionResult, including meta/audit/debug; not an HTTP string |
| `request->sendAsync()` / `client->sendAsync($request)` | `ResultPromiseInterface<ResultHandle>` |
| `request->resolvedAsync()` | `ResultPromiseInterface<ResolvedResultInterface>` |
| `client->response($resolved)` | ClientResponse for returning a response to the application |

A promise does not guarantee non-blocking I/O; see [actual semantics](../execution/transport.md).
`await()` and token-only waiting concern a [provider operation](../execution/continuation-await.md).
To deliberately read an undecoded body, use RawResponse, not raw().

## Successful responses without a DTO <a id="section-3"></a>

For an ordinary request without a DTO, pagination, `#[Download]`, or an extension
handler, `ExecutionResult::data`, `resolved()->data()`, and `dataOrFail()` return:

| Response | Value |
| --- | --- |
| HTTP 204 or a zero-byte body | `null` |
| JSON `null` | `null` |
| JSON `false`, `0`, or a string | The corresponding value, without replacing it with an array |
| JSON array or object | A PHP array; both `{}` and `[]` yield `[]` |
| Nonempty `text/plain` | The original string, including spaces and line breaks |
| Another explicitly specified non-JSON Content-Type | The original string, even if the body looks like JSON |
| Nonempty response without `Content-Type` | Strictly parsed as JSON |

JSON is recognized by `application/json` and the `+json` suffix; MIME case and
parameters such as `charset` do not affect selection. Invalid JSON, including a
whitespace-only body, causes `response_decoding_error` with the original HTTP response.
HTTP 204 is not parsed even if a body exists. `null` is valid in a successful result:
check `isSuccess()`, not data presence; `dataOrFail()` returns this `null` without throwing.

Unknown explicit MIME types return raw text without a DTO. Register an extension
to decode a specialized format. `#[Download]` returns `FileResponse`; a selected
extension handler receives the original response. If it returns `null`, standard
parsing applies.

With a declared DTO, an empty body/JSON `null` still enters hydration as an empty set
of fields; the result depends on required DTO fields and defaults. Pagination retains
its array contract. Scalars in place of DTO or pagination data cause `hydration_error`.
A nonempty non-JSON response for DTO/pagination without an extension handler causes
`response_decoding_error`, reason `unsupported_response_content_type`.

`BeforeHydrate` retains its array signature and runs only for arrays. Ordinary
`null`, scalars, and text skip it; `AfterResponse` and `AfterHydrate` still run.
See [hooks](../extensions/hooks.md#section-6) and [error classification](errors.md#section-9).

To deliberately read an undecoded body, use optional
[`#[RawResponse]` or `withRawResponse()`](../attributes/response.md#section-7).
`ResultHandle::raw()` itself returns ExecutionResult and does not disable JSON decoding.

## ExecutionResult <a id="section-4"></a>

`$execution = $handle->raw()` returns the full execution result. This is a readonly
object with public properties; data and diagnostics are available independently of
the chosen application representation. Its `status` describes SDK execution;
the HTTP status is `$execution->response?->status` when a response was received.

| Property | Contents |
| --- | --- |
| `data` | Prepared data: DTO, collection, array, scalar, or null, depending on the operation |
| `status` | `ResultStatus::SUCCESS`, `PARTIAL`, or `FAILED` |
| `errors` | `ErrorCollection` with original execution and transport errors |
| `validationErrors` | A separate array of input field validation errors |
| `exception` | The original exception or null |
| `response` | The received `ProviderResponse` or null; available without debug |
| `meta` | Operation metadata, such as pagination or composite result metadata |
| `nested` | An array of child request results |
| `requestClass` | The request class or null |
| `traceId` | The trace identifier or null |
| `audit`, `debug` | Execution timeline and optional debug snapshot; [recording and export rules](observability.md) |

`isSuccess()`, `isPartial()`, and `isFailed()` check status; `hasData()` checks for
a non-null value. `hasErrors()` checks errors; the separate validationErrors list
has `hasValidationErrors()` and `validationErrorFor($field)`. `throw()` throws the
stored exception or SdkException only on `FAILED`; for other statuses it returns
the same ExecutionResult.

To inspect the prepared HTTP request:

- `requestDebug()` -> `?array` (`method`, `url`, `headers`, `bodyRaw`, `hasStream`, `oneOf`).
- `requestDebugJson()` -> `?string` (the snapshot as JSON).

By default, `requestDebug*` masks sensitive headers (`Authorization`, `Cookie`, `X-Api-Key`, etc.).

## ResultHandle <a id="section-5"></a>

A handle exposes a completed result without resending. Obtain it through `send()`
or `sendAsync()->wait()`, then use raw/resolved/dataOrFail. A new send/sendAsync
starts a separate execution.

```php
$handle = $request->send();

$raw = $handle->raw();          // ExecutionResult
$resolved = $handle->resolved();// ResolvedResultInterface
$data = $handle->dataOrFail();  // throws on FAILED
$token = $handle->continuationToken(); // ?string
$tokenStrict = $handle->continuationTokenOrFail(); // string or SdkException
$final = $handle->await();      // unified async-await (if the continuation contract is configured)

$request = $raw->requestDebug();     // ?array
$requestJson = $raw->requestDebugJson(); // ?string

// Convenience access through ResultHandle:
$request2 = $handle->requestDebug();      // ?array
$requestJson2 = $handle->requestDebugJson(); // ?string
```

## ResolvedResult <a id="section-6"></a>

`$resolved = $handle->resolved()` returns `ResolvedResultInterface`; the standard
implementation is `ResolvedResult`. This is the application view: methods expose
data and status, and errors are converted into convenient `ClientError` objects.

| Method | Returns or checks |
| --- | --- |
| `data()` | Prepared data, including DTOs; the method itself does not throw because of FAILED status |
| `isSuccess()`, `isPartial()`, `isFailed()` | SDK execution status |
| `hasData()` | Data is non-null; a successful null response returns false |
| `hasErrors()`, `errors()` | Presence and collection of original execution errors; validationErrors are available through `result()` |
| `error()`, `errorViews()` | The first application error or the full ClientError array |
| `message()` | A brief error message or null |
| `result()` | The original ExecutionResult with every field and diagnostic |

In the standard view, `$resolved->data()` is the same data as `$execution->data`,
and `$resolved->result()` is the same object as `$handle->raw()`. With `PARTIAL`,
data may coexist with errors; check status separately. Connect a custom view through
a [factory](#section-10).

### ResolvedResult: convenient error access <a id="section-7"></a>
```php
$resolved = $request->send()->resolved();

$error = $resolved->error();          // ?ClientError (first error)
$code = $resolved->errorCode();       // ?string
$message = $resolved->errorMessage(); // ?string
$status = $resolved->errorStatus();   // ?int
$providerTraceId = $resolved->errorProviderTraceId(); // ?string
$token2 = $resolved->continuationToken(); // ?string

// Full error list (batch/pool/composite)
$views = $resolved->errorViews();     // array<ClientError>

// Typed context (if a factory is configured)
$context = $resolved->errorContext();  // ?object
$contexts = $resolved->errorContexts();// array<object|null>
```

`errorCode()` returns the first available code in this order:
`appCode → clientCode → providerCode → sdkCode`.

`ClientError` belongs to `ApiSutra\VO\Errors`.
Raw context is always available through `$resolved->error()?->context`.
`errorProviderTraceId()` returns `null` if typed context is absent or lacks `providerTraceId`.

**`providerTraceId` in context:** add this field **only** with confirmed provider-side
tracing support, established by documentation or actual responses. Without confirmation,
do not introduce it; see [provider methodology](../../guides/sdk/first-operation.md#section-1).

`errorRetryable()` and `errorCategory()` return `null` by default; define them in the provider SDK.

`continuationToken()` and `continuationTokenOrFail()` use
`ClientConfig::continuationTokenExtractor`. Without an extractor,
`continuationToken()` returns `null`.

### Typed error context <a id="section-8"></a>
Set `errorContextFactory` in `ClientConfig` to obtain typed context.
Without a factory, `errorContext()` returns `null` and `errorContexts()` returns an empty array.

### System context keys <a id="section-9"></a>
The core standardizes these keys:
- `traceId`.
- `httpStatus`.
- `requestClass`.
- `providerCode` (after mapping to `ClientError`).

They are available as `SystemErrorContextKeys` in `ApiSutra\VO\Errors`.

Merge order:
1) Core system context.
2) `RequestError.context`.
3) Context from `ClientErrorMapper` (last write wins).

## Representation factories <a id="section-10"></a>

`ClientConfig::resolvedResultFactory` sets a `ResolvedResultFactoryInterface` with
`make(ExecutionResult): ResolvedResultInterface`. Without an override,
`ResolvedResultFactory` is used. A custom result must preserve the full interface,
including status, errors, and access to the original ExecutionResult.

The [custom result and factory recipe](../../guides/recipes/custom-result.md) shows
complete delegation, client setup, and IDE typing in an executable example.
Standard `ResolvedResult` is `final`; wrap it with an interface implementation.
Pass error mapping, context, and token dependencies explicitly to a custom factory.

`responseFactory` sets `ClientResponseFactoryInterface` with
`make(ResolvedResultInterface): ClientResponse`. Without an override, the standard
response uses 200 for success, 207 for partial, and the status selected by the error
mapper for failure. To change only error representation, `errorMapper` is sufficient;
a custom factory for the entire result is unnecessary. See [errors and mapping](errors.md).

Laravel application HTTP responses use
`ApiSutra\Contracts\Interfaces\Response\ClientResponseAdapterInterface`.
[Adapter contract](https://github.com/apisutra/laravel/blob/master/docs/en/reference/integrations/laravel.md#section-9).

A partial result, such as after pagination or composite execution, may contain both
data and errors: check `isPartial()` and `errors()`. Accessing a raw response through
debug requires `debug: true`; safe export is covered in [observability](observability.md).

## Provider ResultMetaExtractor <a id="section-11"></a>
```php
use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResultMetaExtractorInterface;

final readonly class ProviderEnvelopeMeta implements ResultMeta
{
    public function __construct(
        public ?int $resultCode,
        public ?string $resultMessage,
        public ?string $operationToken,
    ) {}
}

final class ExampleResultMetaExtractor implements ResultMetaExtractorInterface
{
    public function extract(ExecutionResult $result): ?ResultMeta
    {
        $payload = $result->response?->json();
        if (!is_array($payload)) {
            $payload = $result->errors->first()?->response?->json();
        }

        if (!is_array($payload)) {
            return null;
        }

        $code = $payload['resultCode'] ?? null;
        $message = $payload['resultMessage'] ?? null;
        $token = $payload['operationToken'] ?? null;

        if (!is_int($code) || !is_string($message) || !is_string($token)) {
            return null;
        }

        return new ProviderEnvelopeMeta(
            resultCode: $code,
            resultMessage: $message,
            operationToken: $token,
        );
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    resultMetaExtractor: new ExampleResultMetaExtractor(),
);
```

Purpose:
- Move technical envelope fields (`resultCode/resultMessage/operationToken`) out of endpoint DTOs.
- Centrally populate `ExecutionResult->meta` for ordinary requests.

Application invariants:
- If `ExecutionResult->meta` is already set (pagination/batch/composite), the extractor does not overwrite it.
- Returning `null` leaves the result unchanged.
- With `resultMetaExtractor = null`, behavior is unchanged.
- `ExecutionResult->response` is available regardless of `debug=true/false`; the
  extractor must not depend on debug mode.

## Result correlation <a id="section-12"></a>

`ExecutionResult::trace` contains `traceId`, `executionId`, and `parentExecutionId`.
Localization and raw/resolved reads do not change identity; batch/pool retain the full
child error snapshot before throwing.
[Trace, audit, and logs](observability.md#section-5).

## Metadata lifecycle <a id="meta-lifecycle"></a>

The extractor runs once per request result lacking meta: SUCCESS, PARTIAL, or FAILED,
including pages, auth refresh, and polls. Existing meta is not overwritten. Standalone
batch/pool and direct paginator do not add an extractor call for their aggregate.
An extractor failure makes SUCCESS/PARTIAL fail; for an existing FAILED it appends a
secondary error without replacing the original exception, response, or children.
Localization and terminal diagnostics follow extraction, before public delivery.

Async delivery, type inference and chains: [typed promises](promises.md).
