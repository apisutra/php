<!-- languages --> <a href="continuation-await.md">English</a> · <a href="../../../ru/reference/execution/continuation-await.md">Русский</a> <!-- /languages -->
# Waiting for an operation result <a id="section-1"></a>

## ContinuationTokenExtractor <a id="section-2"></a>
```php
final class ExampleContinuationTokenExtractor implements ContinuationTokenExtractorInterface
{
    public function extract(ExecutionResult $result): ?string
    {
        $data = $result->data;
        if (!is_array($data)) {
            return null;
        }

        $token = $data['operationToken'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    continuationTokenExtractor: new ExampleContinuationTokenExtractor(),
);
```

The extractor defines a shared way to obtain a continuation token through `resolved()`:
- `$request->send()->resolved()->continuationToken()`.
- `$request->send()->resolved()->continuationTokenOrFail()`.
- `$request->send()->continuationToken()` and `continuationTokenOrFail()` through `ResultHandle`.

Without an extractor, the token is considered absent (`null`).

## Provider Async Await defaults <a id="section-3"></a>

```php
use ApiSutra\Enums\Continuation\ContinuationMode;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    continuationTokenExtractor: new ExampleContinuationTokenExtractor(),
    continuationModeApplicator: new ProviderContinuationModeApplicator(),
    defaultContinuationMode: ContinuationMode::Auto,
    defaultPollRequest: GetAsyncResultRequest::class,
);
```

- `defaultContinuationMode`: the default provider execution mode for requests without a runtime override.
- `defaultPollRequest`: the default poll request for `awaitByToken()`/`awaitByTokenAs()`.
- `continuationModeApplicator`: provider-specific mapping of `ContinuationMode` to the actual protocol (`query/body/header`).
- `continuationStateResolver`: a `ContinuationStateResolverInterface` instance for explicit
  Pending/Ready/Failed detection. Applied after `ContinuationResult::stateResolver`
  and the built-in resolver for nonempty `unwrap`; required for `awaitByTokenAs()`.

Detailed API and contracts: [Provider Async Await](../../guides/recipes/continuation.md).

The defaults above assume a criterion in the request's `ContinuationResult`:
`stateResolver` or a nonempty `unwrap`. For `awaitByTokenAs()`, and for waiting
without such a declaration, add a client `continuationStateResolver`.

The unified API for long-running scenarios reads the continuation token from the
`resolved()` result, rather than through a provider-specific resource helper.

## What it means <a id="section-4"></a>

A `continuation token` identifies an operation. The provider returns it in the
start response (for example, `operationToken`, `taskId`, or `jobId`) so that the
operation's status or final result can be retrieved later.

In `apisutra`, a pluggable strategy extracts the token:
- `ContinuationTokenExtractorInterface`.
- `ClientConfig::continuationTokenExtractor`.
- `ResolvedResultInterface` convenience methods:
  - `continuationToken(): ?string`.
  - `continuationTokenOrFail(): string`.

Without an extractor, behavior is safe and predictable: `continuationToken()` returns `null`.

This guide covers obtaining the token. The complete async-await API
(mode/applicator/polling) is covered separately:
[Provider Async Await](../../guides/recipes/continuation.md).

## Basic example <a id="section-5"></a>

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Result\ContinuationTokenExtractorInterface;
use ApiSutra\Result\ExecutionResult;
use Override;

final class ProviderContinuationTokenExtractor implements ContinuationTokenExtractorInterface
{
    #[Override]
    public function extract(ExecutionResult $result): ?string
    {
        $payload = $result->response?->json();
        if (!is_array($payload)) {
            return null;
        }

        $token = $payload['operationToken'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.provider.test',
    continuationTokenExtractor: new ProviderContinuationTokenExtractor(),
);
```

## Application usage <a id="section-6"></a>

```php
$handle = $client->operations()->start($request);

$token = $handle->continuationToken(); // ?string
$required = $handle->continuationTokenOrFail(); // string, otherwise SdkException

// Equivalent through resolved():
$resolvedToken = $handle->resolved()->continuationToken();
```

To wait for the operation's final result as well as obtain the token, use:
- `$handle->await()` / `$handle->awaitAs(...)`.
- `$client->continuation()->awaitByToken(...)`.

A token alone does not determine the operation's state. Waiting also requires
`ContinuationResult::unwrap` or a readiness resolver. Ready is hydrated, Pending
continues polling, and Failed stops it even when a token exists. Details
are in the [await contract](../../guides/recipes/continuation.md).
Reading HTTP JSON in this example also works with `Returns`, when `$result->data` is already a DTO.

## Recommendations for provider SDKs <a id="section-7"></a>

- Do not hardcode token keys in the `apisutra` core.
- Implement the extractor in the provider package, where the payload contract is known.
- Return `null` when the token is missing or invalid.
- Use `continuationTokenOrFail()` when the contract requires a token.

## Continuation await errors <a id="section-8"></a>

`ContinuationAwaitException` belongs to `ApiSutra\Exceptions\Continuation`
and extends `SdkException`. Its `reason` distinguishes:

| reason | Cause |
| --- | --- |
| `final_hydration_failed` | The Ready payload cannot be converted into the final DTO, including an invalid shape |
| `final_not_ready` | Sync received Pending |
| `continuation_token_missing` | Pending without a token in a result without an error |
| `attempts_exhausted` | The result remains Pending after the last allowed poll |
| `continuation_failed` | The resolver returned Failed for a result without an error |

`attempts` counts evaluated responses; `lastResult` is the last `ExecutionResult`
with its HTTP response. For `final_hydration_failed`, previous contains the original
`HydrationException`, whose path includes `unwrap`/the resolver path. An invalid
Ready payload shape produces `unexpected_response_shape` with the payload path or `$`.
This also applies when changing type in a cached `awaitAs()`.

`context()` and automatic logs contain reason, attempts, httpStatus, traceId, and
nested hydration context. They exclude the payload, token, and field values; the
original response is explicitly available through `lastResult`, regardless of debug.
Failed and Pending without a token deliver the original failed-result exception
through `throw()`; resolver and DTO configuration exceptions are not wrapped.
Invalid await configuration remains `ContinuationConfigurationException`.

Readiness criteria, examples, and [readiness rules](continuation-await.md#section-10)
are described in the await guide. Initial `send()`/`raw()` retains result-first and
`throwOnErrors` behavior; an await error does not replace that result.

## Limits, caching, and diagnostics <a id="section-9"></a>

`ContinuationAwaitOptions(maxAttempts: 30, intervalMs: 1000)` limits the number of
poll requests. The start response does not consume this limit; there is no pause
after the last poll. The error's `attempts` counts evaluated responses, including
the start in Auto/Sync: with `maxAttempts: 1` and two Pending responses, it is 2
in Auto and 1 in Async.

Repeating `await()` or `awaitAs()` with the same type returns the cached result.
A different type in `awaitAs()` is hydrated from the stored Ready payload without
HTTP; a conversion error preserves the previous cache. All entry points use the
client's hydrator. For a custom `ClientInterface`, the service constructor requires
an explicit hydrator: `new ContinuationService($client, $hydrator)`;
`Hydrator::default()` is allowed.

`ContinuationService::resolveFromStartResult()` returns a `ContinuationOutcome`
with `value`, original `payload`, its `path`, `lastResult`, and `attempts`.
`hydrateOutcome($outcome, $type)` converts that payload to another type.
`awaitFromStartResult()`, `awaitByToken()`, and `awaitByTokenAs()` return the value.

Await errors are covered in the [error section](continuation-await.md#section-8).
The HTTP response is available through `ContinuationAwaitException::lastResult`
regardless of debug. Automatic logging includes safe `context()`, without payload or token.

## Explicit readiness <a id="section-10"></a>

Declare `unwrap` or a resolver. A final result at the root without a wrapper needs
a resolver that explicitly returns Ready. Missing/null `unwrap` does not substitute
the root payload for the path. A Ready conversion error immediately produces
`ContinuationAwaitException(final_hydration_failed)` with the original
`HydrationException` in previous. Handle limits, missing tokens, and Sync not-ready
states as runtime await errors; `ContinuationConfigurationException` is for invalid
configuration.

## External rules for the final DTO <a id="section-11"></a>

The client's hydrator applies `hydration` to Ready payloads and repeated `awaitAs()`.
A strict error is wrapped as `final_hydration_failed` immediately, even with a token.
The final-result path contributes to `sourcePath`; Ready without a declared path
has Unavailable provenance. See [external rule diagnostics](../dto/diagnostics.md#section-3).

## Await correlation <a id="section-12"></a>

`ContinuationOutcome::trace` and `audit` describe the await execution; `lastResult`
remains the last actual response. Await inherits the start's trace, and polls link
to the await through `parentExecutionId`. Standalone awaitByToken creates a root
from the client default or a new trace. An expired HTTP-start budget is not inherited.

`ContinuationAwaitException::trace` and context contain the await identity;
`attempts`, `reason`, `lastResult`, and the original cause are preserved. A cached
await of the same type creates no execution. Cached awaitAs for another type creates
a child conversion without HTTP; a failure leaves the previous outcome available.
See the [shared correlation model](../results/observability.md#section-5).

## Internal poll execution <a id="canonical-poll"></a>

Polls use the client's executor, forcing Single without changing the source request
or its options. They receive the await trace, their own HTTP budget, and the client's
metadata/locale/factory policy. Public throwOnErrors does not interrupt a Pending
resolver decision. Final failed-result delivery occurs after the logical scope's
terminal event and uses [the exception selector](../results/exceptions.md).
The initial public send remains a separate boundary and may throw before await starts.
