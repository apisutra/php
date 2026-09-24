<!-- languages --> <a href="errors.md">English</a> · <a href="../../../ru/reference/results/errors.md">Русский</a> <!-- /languages -->
# Errors and delivery policy <a id="section-1"></a>

[LocalizationConfig](../client/localization.md) selects the language of SDK-owned
messages; English is the default, with Russian built in. Technical codes and
third-party text are preserved.
## throwOnErrors <a id="section-2"></a>
```php
use ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    throwOnErrors: true,
);
```

`throwOnErrors` controls final public delivery, including batch, pool, paginator,
and sendInContext. The default is false.

| Final status | false | true |
| --- | --- | --- |
| SUCCESS / PARTIAL | Return / fulfill | Return / fulfill |
| FAILED | Return / fulfill the result | Throw / reject |

Internal execution always returns a canonical result. FailStrategy and continuation
readiness are evaluated before delivery; they are independent of this setting.

The optional [result exception factory](exceptions.md) selects the exception class for
`dataOrFail()`, `throw()`, and `throwOnErrors`. It runs after internal auth/retry
choices and preserves the original error classification. The `errorMapper` below
changes the error representation, not the exception being thrown.

## ErrorContextFactory <a id="section-3"></a>
```php
use ApiSutra\VO\Errors\ClientError;

final readonly class ExampleErrorContext
{
    public function __construct(
        public ?string $traceId,
        public ?string $target,
    ) {}
}

final class ExampleErrorContextFactory implements ErrorContextFactoryInterface
{
    public function make(ClientError $error): ?object
    {
        return new ExampleErrorContext(
            traceId: $error->context['traceId'] ?? null,
            target: $error->context['target'] ?? null,
        );
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    errorContextFactory: new ExampleErrorContextFactory(),
);
```

Without `errorContextFactory`, `errorContext()` returns `null` and `errorContexts()`
returns an empty array. Raw context is available through `ClientError::context`.

System context keys: `traceId`, `httpStatus`, `requestClass`, `providerCode`.
Merge order: system → `RequestError.context` → context from `ClientErrorMapper`.

**`providerTraceId`:** add it to typed context **only** if the provider is confirmed
to return trace IDs in error responses. See [provider methodology](../../start/create-sdk.md).

## ClientResponseFactory / ErrorMapper <a id="section-4"></a>

```php
use ApiSutra\Response\ClientResponse;
use ApiSutra\Response\ClientResponseFactoryInterface;
use ApiSutra\VO\Errors\ClientError;
use ApiSutra\VO\Errors\ClientErrorMapperInterface;
use ApiSutra\VO\Errors\RequestError;

final class ExampleClientResponseFactory implements ClientResponseFactoryInterface
{
    public function make(ResolvedResultInterface $result): ClientResponse
    {
        return new ClientResponse(status: 200, headers: [], body: 'ok');
    }
}

final class ExampleClientErrorMapper implements ClientErrorMapperInterface
{
    public function map(RequestError $error): ClientError
    {
        return new ClientError(
            providerCode: $error->response?->status,
            sdkCode: $error->code,
            message: $error->message,
        );
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    responseFactory: new ExampleClientResponseFactory(),
    errorMapper: new ExampleClientErrorMapper(),
);
```

Without `responseFactory`, the default `ClientResponseFactory` is used.
`errorMapper` is optional and needed only for a custom error format.

`ClientResponseFactory` converts `ResolvedResult` into an application-friendly response.
`ClientErrorMapper` controls error format and the final HTTP status.

## Local rate limits and HTTP 429 <a id="section-5"></a>

A local quota returns `rate_limited` with `reason=local_rate_limit_exceeded` and
`retryAfter` in seconds. If no HTTP attempt has occurred, response is null.
A store or atomic backend failure returns `execution_error` with
`reason=rate_limit_backend_error`. Both reasons stop HTTP retry. An actual 429 response
retains existing HTTP handling. See the [contract and examples](../execution/rate-limit.md#section-5).

`RequestException::$response` is typed `?ProviderResponse`, so handlers throughout
this hierarchy must check response presence. `catch (RateLimitException)` and
retryAfter remain supported. For a local rejection after an earlier HTTP attempt,
that attempt's response is available in the result and exception.lastResponse;
exception.response remains null. [Choosing quotas](../execution/rate-limit.md#section-7).

## Error policy <a id="section-6"></a>
Override behavior in `AbstractRequest` or `AbstractClient` through:
- `hasRequestFailed()`.
- `shouldRetry()`.
- `getRequestException()`.

Resolution order: **request → client → default**.

Default behavior:
- `hasRequestFailed()` → HTTP status `>= 400`.
- `shouldRetry()` → `false`.
- `getRequestException()` → `null`.

Override these for nonstandard provider error signals, such as `status` in the body,
or custom retry/exception criteria.

For a failed HTTP response, the exception selected by this policy also supplies
`RequestError.message`, including its localizable message definition. Without an
exception, the response message is used. Declaring diagnostic sensitive fields does
not replace either message; a request that needs a safe custom message defines it
through `getRequestException()`.

## ClientResponse and error mapping <a id="section-7"></a>
For external APIs, transform the result through:
- `ResolvedResultFactoryInterface` → `ResolvedResultInterface`.
- `ClientResponseFactoryInterface` → `ClientResponse`.
- `ClientErrorMapperInterface` → `ClientError`.
- `ClientErrorFactory` → shared mapping for `ClientResponse` and convenience methods.

## Control-flow exceptions <a id="section-8"></a>
- `EarlyReturnException`: end the pipeline successfully without HTTP.
- `RetryableException`: request retry from the processing layer.

## JSON and execution error classification <a id="section-9"></a>

| Failure | SDK code |
| --- | --- |
| Encoding outgoing JSON | `serialization_error` |
| Parsing a nonempty JSON response | `response_decoding_error` |
| Converting response data to a DTO | `hydration_error` |
| User hook | `hook_error` |
| PSR-18 network failure | `connection_failed` |
| Confirmed timeout | `timeout` |
| PSR-18 request failure | `invalid_request` |
| Other PSR-18 client failure | `transport_error` |
| Local file reading, writing, or publication | `file_transfer_error` |
| Configuration | `configuration_error` |
| Unknown execution exception | `execution_error` |

Confirmed Guzzle/cURL send/read errors 55/56 and empty-response error 52 are classified
as `connection_failed`; code 28 is `timeout`. The original exception is retained in
previous; error text is not used to guess a network code. The PSR-18 path does not
require Guzzle Client.

### Transmission state <a id="section-10"></a>

`TransportException::transmissionState` uses the `TransmissionState` enum from
`Enums\Http` and describes one attempt. The default is `Unknown` (`unknown`): the
send outcome is unknown. `NotSent` (`not_sent`) is allowed only with evidence that
the request was not sent. A custom transport is responsible for that assertion.
Timeout, empty response, and interrupted reads/writes do not prove non-transmission.
Neither a generic PSR network failure nor a DNS errno alone gives that guarantee.

For a transport failure or local deadline, inspect
`$result->errors->first()?->context['transmissionState']`:
this state accumulates across all attempts of the current request. An earlier unknown
attempt is not erased by a later not_sent. A deadline before the first send yields
not_sent; after a possible send, unknown. Auth and child requests have their own
states; this is not the status of the application's entire business operation.
The pipeline preserves the current request's accumulated state in ExecutionDeadlineException.

These diagnostics do not authorize automatic POST retry or prove whether the server
performed the operation. After losing a response, the application may use a provider's
result check or idempotency key.

### Response decoding <a id="section-11"></a>

`SerializationException`, `ResponseDecodingException`, and `HydrationException` belong
to `Exceptions\Serialization`. Standard JSON codec errors preserve `JsonException`
in `previous`; a parsing failure's HTTP response remains in `ExecutionResult::response`.
The pipeline check applies to nonempty `application/json`, MIME types with a `+json`
suffix, and responses without `Content-Type`; successful status 204 is excluded.
Null, empty-body, and text behavior is described in the
[successful response contract](handles.md#section-3).
Public `ProviderResponse::jsonStrict()` performs strict parsing when explicitly called;
existing `json()` retains permissive behavior for compatibility, including custom
HTTP error handlers for ordinary string responses. Streamed downloads require
explicit reading of `ProviderResponse.stream`: `json()` and `jsonStrict()` produce
`configuration_error`. Extension processing outside downloads remains active in Auto;
explicit [RawResponse](../attributes/response.md#section-7) bypasses format handlers
and JSON. See [file responses](../../guides/recipes/files.md).

### Shape and numeric range errors <a id="section-12"></a>

DTO field contract violations, strict `Returns::unwrap`, JsonCast, and integer guards
return `hydration_error` with `HydrationException`. For these failures,
`RequestError::context` and exception properties contain `reason`, `path`, `expected`, and `actual`:

| reason | Meaning |
| --- | --- |
| `response_type_mismatch` | The final value violates active Returns; [messages and boundaries](exceptions.md). |
| `unwrap_path_missing` | The specified path is absent; actual is `missing`. |
| `unexpected_response_shape` | The path contains null/scalar instead of declared DTO data, or a nonempty list instead of a single Nested object. |
| `integer_out_of_range` | The number does not fit in int; actual is the source value's type. |
| `required_field_missing` | A required field is absent. |
| `null_not_allowed` | The final null value is not allowed by the declared type. |
| `invalid_field_type` | The value after casts does not match the field or parameter type. |
| `invalid_json` | JsonCast cannot parse a nested JSON string. |
| `invalid_datetime` | The selected Throw policy rejects the date; expected includes the format. |
| `unknown_nested_variant` | Nested in Error mode found no variant; expected contains the declared allowed variants. |

For example, `path=data.item.id`, `expected=int`, `actual=string` identifies an
overflowing field. Nested DTOs use property names; collections use ordinal indices
(`items[1].id`), without external keys or values in diagnostics. These fields describe
the new checks; they may be null for other HydrationException instances.

Structured `DefaultValue` provider errors also include the current field name,
for example `data.child.count`. Rules and the change from the previous incomplete
path are in the [provider contract](../dto/defaults.md#section-9).

The original HTTP response remains in the result. Hydration errors do not trigger
another HTTP retry. `raw()`/`resolved()` report the error; `dataOrFail()` and
`throwOnErrors` throw it. Sync and promise APIs share this contract.
See [Returns](../attributes/response.md#section-5) for unwrap behavior and
[serialization](../dto/scalars.md#section-4) for numeric types.

Detailed exception fields are covered in [DTO diagnostics](../dto/diagnostics.md#section-4).

### File and transport errors <a id="section-13"></a>

`FileTransferException` from `Exceptions\Files` contains the stage, `bytesWritten`,
and `partial`; these are also available in error context. A final-write error retains
the accepted response's HTTP status. Local errors do not cause HTTP retry, even with
general `Throwable` in `retryExceptions`. A deadline during copying retains the
`timeout` code with partial-write details.

Known transport exceptions are normalized before retry decisions. The original
exception is available in `previous`; an explicitly configured original class in
`retryExceptions` is still honored. Timeout is not inferred from message text:
the SDK supports its own `TimeoutException`, the total retry budget, and confirmed
cURL errno 28 in Guzzle ConnectException. Other transports do not require Guzzle HTTP Client.

After retries are exhausted, the last HTTP response goes through normal error mapping.
For example, 503 remains `service_unavailable`, including HTML or malformed JSON
responses. Status 400 uses `bad_request`; other unmapped 4xx statuses use `client_error`.
The response and its raw body are preserved. A string `message` from JSON becomes
the message; otherwise `HTTP <status>` is used. If the last HTTP attempt ends in a
network failure, the previous attempt's response is not substituted for the missing
current response.

An arbitrary hook exception does not become a network error or trigger network retry.
It remains in `ExecutionResult::exception` as the original object. Typed HTTP/configuration
exceptions retain their codes. `EarlyReturnException` also works at AfterResponse,
ending successfully; `RetryableException` retains its special control-flow semantics.
DTO configuration errors are not disguised as hydration data errors.

These rules are the same for sync, promise API, and batch: `throwOnErrors` changes
exception delivery, not the failure cause. This release does not change idempotency
policy or stream replay.

## Total budget exhaustion <a id="section-14"></a>

`ExecutionDeadlineException` is a `TimeoutException`. In `raw()->errors`, the code is
`timeout`, with `reason: execution_deadline_exceeded` and the stopping `stage` in context.
This is a final execution error even if the last HTTP response was 200 or 503.
An available actual response is retained for diagnostics; before the first HTTP call,
there is no response. `raw()`/`resolved()` show the error; `dataOrFail()` and
`throwOnErrors` throw it. In the exception, `response` retains the available response
and `getPrevious()` retains the original cause. An individual attempt timeout may
allow safe retry; a total deadline does not.
Full contract: [timeouts and delay](../execution/deadlines.md).

### Unavailable validation <a id="section-15"></a>

If `#[Validate]` rules are declared but a compatible factory is unavailable or its
provider cannot supply one, the request ends with `configuration_error` before HTTP.
`validationErrors` is empty because data was not validated. Invalid data with a
working factory still yields `validation_failed` with field errors.
Direct `validate()`/`isValid()`/`errors()` calls without an available engine throw
`ConfigurationException`. [Contract](../client/validation.md#section-11).

If auth refresh fails after a main 401, both responses are retained: the main response
in `response`, and refresh in `AuthRefreshFailedException::dependencyResult`.
Automatic context contains `reason=auth_refresh_failed`, without credentials or
the full dependency result. [Recovery contract](../auth/tokens.md#section-11).

Batch/pool preserve classification and actual responses for HTTP, decoding, hydration,
configuration, and hook errors regardless of throwOnErrors. `connection_failed`
means a confirmed network failure; an unknown exception yields `execution_error`.
Existing differences between strategies' exception delivery remain.
A [recording](../testing/fixtures.md#section-3) error may accompany HTTP 200: the main
operation has already completed and must not be repeated as if it were a network failure.

Local cooldown failures use rate_limited with reason server_cooldown_active, distinct from local_rate_limit_exceeded. See [CooldownException, timing and response context](../execution/cooldown.md#errors).
