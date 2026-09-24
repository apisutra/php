<!-- languages --> <a href="diagnose.md">English</a> · <a href="../../ru/start/diagnose.md">Русский</a> <!-- /languages -->
# Diagnose a problem <a id="section-1"></a>

The goal is reproducible input, an observed error, and the exact violated contract. Start with the operation and its configuration, rather than internal core stages.

## Collect facts <a id="section-2"></a>

Record PHP, ApiSutra, and SDK versions; the request class, transport type, and whether Laravel and an external DTO rule set are configured. Save an anonymized original response, its HTTP status/Content-Type, and a minimal call.

Obtain `ExecutionResult` through `send()->raw()` or `resolved()->result()`. Check the status, errors, and original exception. Share [safe debug output and logs](../reference/results/observability.md) with colleagues, rather than the full raw context.

## Choose a check <a id="section-3"></a>

| Observation | Check |
| --- | --- |
| No HTTP call yet | [Validation](../reference/client/validation.md), [request construction](../reference/serialization/request-parts.md), configuration |
| Wrong address or parameters | [URI/query](../reference/serialization/uri-query.md) and client binding |
| 401, refresh, or wrong scope | [Auth](../reference/auth/README.md) |
| Wrong type, missing, Nested | [DTO diagnostics](../reference/dto/diagnostics.md), strict, and input shape |
| Retries, 429, waiting | [Retry, quotas, deadline](../reference/execution/README.md) |
| Await did not complete | [Readiness](../reference/execution/continuation-state.md), token, and poll limit |
| Unexpected or missing request field | [DX/wire](../reference/serialization/dto-output.md), [receiver](../reference/serialization/receiver-output.md) |

## Confirm the cause <a id="section-4"></a>

Replace HTTP with a [local fixture](../reference/testing/fixtures.md), preserving the actual config/rules/unwrap. Compare the promised contract with the result. If a supported API offers a workaround, document its limitations. If the contract is violated, prepare a minimal example with expected and actual results.

[Common symptoms](../guides/troubleshooting.md) provides short, ready-to-use checks. Do not change ApiSutra src in a consumer SDK for diagnostic purposes.
