<!-- languages --> <a href="first-operation.md">English</a> · <a href="../../../ru/guides/sdk/first-operation.md">Русский</a> <!-- /languages -->
# The first end-to-end operation <a id="section-1"></a>

Start with an operation whose request, successful response, and error are known.
In the [tutorial SDK](../../examples/sdk.md), this is retrieving a record by id.

## Build the path from configuration to DTO <a id="section-2"></a>

1. The [configuration factory](../../../example/sdk/src/Config/ClientConfigFactory.php)
   sets baseUrl and DTO rules. Auth and protocol defaults belong at this level.
2. The [client](../../../example/sdk/src/DemoClient.php) explicitly receives configuration
   and transport; a resource method returns a request bound to that client.
3. The [request](../../../example/sdk/src/Resources/Records/Get/GetRecordRequest.php)
   sets method/path and input placement. Use the [request declaration](../../reference/request/declaration.md).
4. The [DTO](../../../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php)
   describes the required data. Choose [attributes or external rules](../../start/describe-dto.md),
   then set `Returns` and unwrap according to the confirmed response shape.
5. The [runner](../../../example/sdk/run.php) demonstrates success through dataOrFail()
   and failure through resolved(). Check the prepared parameters and both results.

## Errors and statuses <a id="section-3"></a>

Map provider codes to public SDK codes centrally through an
[error mapper](../../reference/results/errors.md). Global codes belong to the
provider-wide layer, local codes to the operation; matching numbers do not make them
one enum. For custom process statuses, define an enum and interpretation function in the SDK.

`appCode` normally remains null: a general-purpose SDK publishes `clientCode`, which
the application maps to its own codes. Domain applications can explicitly choose another contract.
Add `providerTraceId` only when provider support is confirmed;
a fabricated value makes diagnostics worse.

Read technical envelope fields through
[ResultMetaExtractor](../../reference/results/handles.md#section-11).
It must not perform I/O, depend on debug, or fail when metadata is absent.
A custom ResolvedResult is useful for typed access to this metadata; its factory
preserves the common result contract. Do not copy the technical envelope into every
business model solely for application access.

## Add capabilities as needed <a id="section-4"></a>

| API condition | Next step |
| --- | --- |
| Paged data | [Pagination](../recipes/pagination.md), itemsType, and a metadata resolver |
| Long-running operation and token | [Await](../recipes/continuation.md), an explicit Ready/Pending criterion |
| Multiple versions | [Versioning](../../reference/client/versioning.md); explicit version without fallback |
| SDK coverage data | [Operation Inventory](../../reference/client/operation-inventory.md) |
| Static dictionaries, capabilities, prices | [Provider catalogs](../../reference/client/catalogs.md) |
| List of SDK response types | [ResponseDtoCatalog](../../reference/client/response-dto-catalog.md) |

These mechanisms are not prerequisites for the first ordinary operation.
Choose them based on API facts and the consumer's task.

## Accept the result <a id="section-5"></a>

Complete the [operation check](../testing/unit.md). The SDK must run without hidden
variables or accidental HTTP in tests. Record limitations and move on to [coverage](coverage.md).
