<!-- languages --> <a href="use-sdk.md">English</a> · <a href="../../ru/start/use-sdk.md">Русский</a> <!-- /languages -->
# Use an existing SDK <a id="section-1"></a>

The SDK's documentation defines its client, resources, credentials, and supported operations. ApiSutra supplies the shared execution and result mechanisms.

## Setup <a id="section-2"></a>

1. Install the SDK and check its PHP, ApiSutra version, and transport requirements.
2. Obtain a client according to the SDK instructions. In Laravel, an existing SDK supplies its own provider and settings; DI access does not require copying the provider. General options: [standalone](../guides/integration/standalone.md) and [Laravel](https://github.com/apisutra/laravel/blob/master/docs/en/guides/integration/laravel.md).
3. Supply credentials through the SDK's intended configuration. Do not put them in response DTOs or fixtures. For multiple accounts, create corresponding client configurations; isolation rules are described in [auth](../reference/auth/README.md).
4. Call a resource method, populate the request, and choose how to obtain the result.

The [client configuration overview](../guides/client/showcase.md) demonstrates auth, timeout, retry, quotas, cache, DTOs, and per-call options in one executable example.

## Results <a id="section-3"></a>

`dataOrFail()` is useful when failure should become an exception. `resolved()` provides data, status, and errors for application branching. `send()` returns `ResultHandle`; its `raw()` provides `ExecutionResult`, not a raw HTTP string. See the [full result contract](../reference/results/handles.md).

Use [pagination](../guides/recipes/pagination.md) to request all pages and [await](../guides/recipes/continuation.md) for a provider's background task. A promise API and a provider's background operation are not the same thing.

## Verify the integration <a id="section-4"></a>

First reproduce one successful and one error response using a [mock](../reference/testing/mocking.md). Check auth scope, baseUrl, timeout, and error handling in your application. Enable live tests separately.

For failures, follow the [diagnostic path](diagnose.md).
