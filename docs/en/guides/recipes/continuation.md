<!-- languages --> <a href="continuation.md">English</a> · <a href="../../../ru/guides/recipes/continuation.md">Русский</a> <!-- /languages -->
# Add operation waiting <a id="section-1"></a>

The [executable example](../../examples/continuation.md) shows an operation start,
two poll requests, and the final DTO. It uses local responses.

## Establish the provider contract <a id="section-2"></a>

1. Identify the field that distinguishes waiting, success, and terminal operation failure.
   A successful HTTP response alone does not mean the result is ready.
2. Describe the start and status-check requests. Connect the final DTO, poll class,
   and resolver through [ContinuationResult](../../reference/attributes/response.md#section-6).
3. Implement a [readiness criterion](../../reference/execution/continuation-state.md).
   The tutorial [OperationStateResolver](../../../example/continuation/src/OperationStateResolver.php)
   distinguishes Pending, Ready, and Failed using the `status` field.
4. Connect a token extractor to the client. In the tutorial SDK this is
   [TokenExtractor](../../../example/continuation/src/TokenExtractor.php).
5. Choose waiting limits and test pending, a ready result, failure,
   exhausted attempts, and an incorrect final DTO type.

## Wait for the result <a id="section-3"></a>

In the [complete example](../../../example/continuation/run.php), `$client` is already
assembled with configuration and transport:

```php
use ApiSutra\Continuation\ContinuationAwaitOptions;
use Example\Continuation\StartRequest;

$handle = $client->send(new StartRequest());
$final = $handle->await(new ContinuationAwaitOptions(maxAttempts: 2, intervalMs: 0));
```

The local example uses a zero interval. For a real API, choose an interval according
to its contract. `maxAttempts` limits the number of poll requests.

Auto/Sync/Async modes, waiting by a saved token, repeated await, and error delivery
are described in the [await contract](../../reference/execution/continuation-await.md).
