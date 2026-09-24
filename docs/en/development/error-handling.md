<!-- languages --> <a href="error-handling.md">English</a> · <a href="../../ru/development/error-handling.md">Русский</a> <!-- /languages -->
# Error delivery in the core <a id="section-1"></a>

A brief description of how the SDK handles errors and builds results.

## Error sources <a id="section-2"></a>

- Local request validation.
- Transport (HTTP/network).
- Hydration (DTO/response format).
- Provider business errors.

## Strategies <a id="section-3"></a>

- By default, the SDK returns `ExecutionResult` with status and errors.
- With `throwOnErrors = true`, the exception propagates to the caller.
- `ClientErrorFactory` + `ClientErrorMapper` map errors in `ClientResponseFactory`
  and the DX methods of `ResolvedResult` (`error()`/`errorViews()`).
- `ErrorContextFactoryInterface` adds typed context for DX (`errorContext()`).
- Core system context: `traceId`, `httpStatus`, `requestClass`, `providerCode`.

Explicit `await()` throws waiting errors regardless of `throwOnErrors`.
`ContinuationAwaitException` retains the last result and failure cause;
failed hydration of a Ready payload does not turn into another poll.
See [await behavior](../guides/recipes/continuation.md).

With external rules, HydrationException distinguishes the DTO path from the original
JSON Pointer. `context()` retains exact data for the result; `logContext()` masks
unknown source keys in automatic logs. See [diagnostic boundaries](../reference/dto/diagnostics.md#section-3).

## Overrides <a id="section-4"></a>

`AbstractRequest` and `AbstractClient` can override:

- `hasRequestFailed()`;
- `shouldRetry()`;
- `getRequestException()`.

Internal execution returns canonical results regardless of public throwOnErrors.
Final public delivery follows [the exception contract](../reference/results/exceptions.md);
[execution owners](execution.md) finish diagnostics before selecting an exception.
