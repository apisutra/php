<!-- languages --> <a href="unit.md">English</a> · <a href="../../../ru/guides/testing/unit.md">Русский</a> <!-- /languages -->
# Test an SDK operation <a id="section-1"></a>

The primary test must pass without an external API, using the SDK's actual configuration,
a fake transport, the original HTTP response, and the operation's public call.

## Prepare the scenario <a id="section-2"></a>

1. Create the client in the same way as an SDK consumer.
2. Configure MockTransport by request class and enable preventStrayRequests().
3. Use anonymized JSON fixtures; do not derive expected values from actual values.
4. Call the resource method and check the prepared method, URL, parameters, and body.
5. Check the DTO or result data, status, errors, and available context.

The [tutorial run.php](../../../example/sdk/run.php) shows success and HTTP 404.
The [mock API](../../reference/testing/mocking.md) covers fake, sequence, callback, and assertions;
[fixtures](../../reference/testing/fixtures.md) covers recording and playback.

## Minimum matrix <a id="section-3"></a>

| Scenario | Check |
| --- | --- |
| Ordinary request | Exact outgoing representation, DTO, and success status |
| Invalid input | Validation/configuration error before HTTP |
| API error | Provider/client code, message, and HTTP status |
| Missing/null/wrong DTO type | Documented response and field path |
| Unknown data | Receiver remainder when enabled, and no receiver on the wire |
| Malformed JSON | Decoding error with access to the original response |

Add separate cases for mechanisms you use: oneOf/discriminator, pagination/meta,
batch/composite, await, files, auth refresh, and Laravel binding.
The [coverage map](../sdk/coverage.md) helps select the required set.

## Validation and diagnostics <a id="section-4"></a>

For `#[Validate]`, provide a working factory or explicitly assert configuration_error
when it is absent. For multiple clients, check the A→B→A sequence; one factory must
not replace another. `Validator::resetFactory()` clears the global factory;
resetting the container registry alone does not clear it.
See [factory precedence](../../reference/client/validation.md#section-6).

For DTO errors, check `reason/path/expected/actual` instead of PHP TypeError text.
[HydrationException](../../reference/dto/diagnostics.md) is thrown directly in standalone use;
raw/resolved retain the error, while dataOrFail/throwOnErrors deliver the exception.
Check access to the original response with debug=false and the absence of a planted
secret in automatic ERROR logs. For JsonCast, distinguish a malformed string from JSON null.

A mock contract does not prove provider behavior: if needed, add a
[separate live check](live.md) with explicit permission and cost control.
