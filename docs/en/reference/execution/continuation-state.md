<!-- languages --> <a href="continuation-state.md">English</a> · <a href="../../../ru/reference/execution/continuation-state.md">Русский</a> <!-- /languages -->
# Determining operation readiness <a id="section-1"></a>

## Building blocks <a id="section-2"></a>

### Final result contract <a id="section-3"></a>

Use the class-level `#[ContinuationResult]` attribute:

```php
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Attributes\Response\Returns;

#[Returns(StartEnvelopeDto::class)]
#[ContinuationResult(
    finalType: FinalBusinessDto::class,
    unwrap: 'data',
    pollRequest: GetAsyncResultRequest::class,
)]
final class CheckRequest extends BaseRequest
{
    // ...
}
```

- `finalType`: the required final DTO for `await()`.
- `unwrap`: the path to final data inside the poll/start response JSON object, and the criterion for its presence.
- `pollRequest`: an optional poll request; defaults to `ClientConfig::defaultPollRequest`.
- `stateResolver`: a `ContinuationStateResolverInterface` class instantiated without arguments.
- `defaultMode`: the request mode; a runtime override takes precedence, followed by the client mode.

Readiness is explicit. Resolution priority is the attribute's `stateResolver`, then
the built-in `FinalPathStateResolver` for a nonempty `unwrap`, then
`ClientConfig::continuationStateResolver`. If none is set, waiting fails with
`ContinuationConfigurationException` before the first poll request.

The built-in resolver reads `$result->response?->json()`. A non-null value at `unwrap`
means Ready; a missing path, null, no response, or a body that is not a JSON object
means Pending. `false`, `0`, and `[]` are present values and count as Ready. The
response root is not substituted for the path. Hydration runs only after Ready:
being able to construct a DTO with defaults is not evidence of readiness.

`finalType` also appears automatically in `$client->responseDtoCatalog()` as an entry
with `kind = ResponseDtoKind::AsyncFinal`, alongside the start DTO from `Returns(...)`
(`kind = Sync`). See [Operation Inventory → ResponseDtoCatalog](../client/response-dto-catalog.md#section-9).

### Provider execution mode <a id="section-4"></a>

Use `ContinuationMode`:
- `Auto`: evaluate the start response; Ready returns the final result, Pending starts polling by token.
- `Sync`: evaluate the start response; Ready returns the final result, Pending raises `final_not_ready`.
- `Async`: skip start-response readiness evaluation and immediately begin polling by token.

Failed stops waiting for any evaluated response, even when a token is present.

Request runtime convenience methods:
- `asProviderSync()`.
- `asProviderAsync()`.
- `asProviderAuto()`.
- `withContinuationMode(...)`.

### Mapping the mode to the provider protocol <a id="section-5"></a>

The core does not hardcode the provider's `async`/`mode`/`header` fields.
`ContinuationModeApplicatorInterface` handles this:

```php
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationModeApplicatorInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Serialization\VO\RequestPartsBag;
use ApiSutra\VO\Pipeline\PipelineContext;

final class ProviderContinuationModeApplicator implements ContinuationModeApplicatorInterface
{
    public function apply(RequestInterface $request, RequestPartsBag $parts, ContinuationMode $mode, ?PipelineContext $context = null): RequestPartsBag
    {
        $parts->query['async'] = [
            'value' => $mode === ContinuationMode::Async,
            'format' => null,
        ];

        return $parts;
    }
}
```

## Custom readiness criterion <a id="section-6"></a>

For a protocol with an operation status, implement a resolver in the SDK. It receives
one `ContinuationContext` for the entire wait: `finalType` (null for an untyped final
result), `unwrap`, `sourceRequestClass`, and `mode`. A resolver neither performs HTTP
nor hydrates DTOs. Its exceptions propagate without wrapping.

The complete [OperationStateResolver](../../../example/continuation/src/OperationStateResolver.php)
maps status: `done` → Ready(data, 'data'), `failed` → Failed, everything else → Pending.
The [executable example](../../examples/continuation.md) checks the start, two polls,
and repeated access to the cached final result.

Set `stateResolver: OperationStateResolver::class` in `ContinuationResult` or
`continuationStateResolver: new OperationStateResolver()` in client configuration.
`ClientConfig::with()` preserves the instance; explicit null removes the setting.
Without `ContinuationResult`, `await()` with a client resolver returns the Ready
payload unchanged, including null. `awaitAs()` sets an explicit type.

## Invariants and configuration errors <a id="section-7"></a>

- The poll request must have **exactly one required scalar constructor parameter** (the token).
- If the attribute omits `pollRequest`, `ClientConfig` must supply `defaultPollRequest`.
- Without a token extractor, `await()`/`awaitByToken()` cannot continue an async scenario.
- A failed poll/start response continues polling only if the resolver returns Pending and a token exists.
- Continuation configuration errors throw `ContinuationConfigurationException`.

The resolver receives canonical poll results even with throwOnErrors true. HTTP failure
alone does not bypass readiness evaluation: Pending with a token may continue. Failed
or Pending without a token delivers the failed result only after the await scope ends.
