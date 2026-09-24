<!-- languages --> <a href="add-operation.md">English</a> · <a href="../../ru/start/add-operation.md">Русский</a> <!-- /languages -->
# Add an SDK operation <a id="section-1"></a>

First find the existing client and a similar operation. Reuse their configuration, DTO rules, resource structure, and testing approach.

## Required information <a id="section-2"></a>

You need a confirmed method/path, parameters, auth scope, a successful response, and at least one error response. Determine whether this is a regular, paginated, or file operation, or one that starts a task and then waits for completion.

## Implementation <a id="section-3"></a>

1. Place the request and its own DTOs in the operation directory; use shared types according to the [ownership rules](../guides/sdk/design.md).
2. Describe the input using the [request guide](../guides/requests.md): path, query, body, oneOf variants, and local validation. Check that no unwanted fields appear on the wire.
3. Add a resource method that returns a request bound to the client. The [example resource](../../example/sdk/src/Resources/Records/RecordsResource.php) demonstrates this step.
4. Set `Returns`, unwrap, and [DTOs](describe-dto.md); use a fixture to verify types, nullability, missing fields, and unknown data.
5. Add [tests](../guides/testing/unit.md) for the prepared request, successful DTO, API error, and malformed response. Test retries only when the operation is confirmed to be safe to repeat.
6. Add the operation to the SDK documentation and [coverage map](../guides/sdk/coverage.md).

## Verify the result <a id="section-4"></a>

The operation is callable through a public resource method, uses the existing client, sends the expected HTTP request, and returns the documented result. Tests make no accidental HTTP calls and have no hidden container dependency. New types do not create competing models of the same entity without an explainable contract difference.

For specialized operations: [pagination](../guides/recipes/pagination.md), [await](../guides/recipes/continuation.md), [files](../guides/recipes/files.md).
