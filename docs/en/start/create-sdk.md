<!-- languages --> <a href="create-sdk.md">English</a> · <a href="../../ru/start/create-sdk.md">Русский</a> <!-- /languages -->
# Create an external API SDK <a id="section-1"></a>

This path produces an SDK with a working first operation, reproducible tests, and an explicit map of further coverage. The [Records SDK](../examples/sdk.md) is a teaching implementation.

## Preparation <a id="section-2"></a>

Gather the API documentation version, base addresses, authentication method, several anonymized responses, and the required operations. Record unconfirmed conditions separately: do not infer limits, error formats, or idempotency from an endpoint name. [API analysis](../guides/sdk/analysis.md) lists the facts to collect.

## Workflow and priorities <a id="section-3"></a>

1. **Define the scope.** Choose one useful operation, its successful response, and an error. Put other operations in the coverage map; first establish a working end-to-end path.
2. **Design the SDK.** Separate client, resources, and operation-local DTOs using the [type ownership rules](../guides/sdk/design.md). Separate services or versions when their protocols and configurations actually differ.
3. **Construct the client.** Set configuration and transport explicitly through [standalone setup](../guides/integration/standalone.md) or [Laravel integration for your SDK](https://github.com/apisutra/laravel/blob/master/docs/en/guides/sdk/laravel.md). Confirm auth and the base URL.
4. **Describe the request.** Follow the [first operation guide](../guides/sdk/first-operation.md) to specify HTTP method, path, query/body, and `Returns`. Local input validation must precede HTTP.
5. **Describe DTOs.** Choose [plain models or attributes](describe-dto.md). Establish mapping, presence, null, and shape first, then transformations and extensions.
6. **Verify the result.** Run successful-response, error, and DTO-drift fixtures using the [testing guide](../guides/testing/unit.md). Check the outgoing payload.
7. **Add complex behavior based on API evidence.** Add pagination, await, retry, cache, and file uploads together with their own scenarios and limits.
8. **Expand and release the SDK.** Work through [coverage](../guides/sdk/coverage.md), then [documentation and release](../guides/sdk/release.md).

## Completion criteria <a id="section-4"></a>

A consumer can install the SDK, construct a client from the documented configuration, call an operation, and receive typed data or an understandable error. Tests do not require the real API; live checks are separate. Unsupported capabilities and unconfirmed limitations are explicitly listed, and fixtures contain no secrets.

Hand over the same results to people and agents: source files, verification commands, observed results, limitations, and the map of further coverage.

## The next operation <a id="section-5"></a>

[Adding an operation](add-operation.md) follows the established structure and rules. Keep external API specifics out of the ApiSutra core: supported [extensions](../reference/extensions/README.md) let you implement them in the SDK.
