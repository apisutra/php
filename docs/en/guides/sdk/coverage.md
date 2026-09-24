<!-- languages --> <a href="coverage.md">English</a> · <a href="../../../ru/guides/sdk/coverage.md">Русский</a> <!-- /languages -->
# API coverage and SDK criteria <a id="section-1"></a>

Maintain an operation map: public SDK call, endpoint/version, DTO, test readiness,
limitations, and confirmation source. Use [Operation Inventory](../../reference/client/operation-inventory.md)
for a snapshot of the implemented API; it does not know about external API operations
that have not yet been implemented, so it cannot replace the planning map.

## Check architecture and models <a id="section-2"></a>

- [] Client, configuration, and transport assembly is reproducible; the example runs.
- [] [Type ownership](design.md) follows resources and operations; Domain contains only
  truly shared concepts, and large models are grouped by meaning.
- [] Shared bases are introduced as needed; plain DTOs with external rules need not
  extend ApiSutra classes. Attribute models bind shared profiles explicitly.
- [] Recurring payloads are extracted into DTOs; input and output names are defined
  separately. Enums and title representation follow the actual user contract.

## Check DTOs and outgoing payloads <a id="section-3"></a>

- [] Types, [missing/null/default](../../reference/dto/defaults.md),
  [mapping](../../reference/dto/profiles.md), and [nesting](../../reference/dto/shapes.md)
  are confirmed by fixtures; strict is not enabled blindly for numeric strings.
- [] Scalar-list elements are checked with an explicit shape or itemCast when required;
  PHPDoc alone is insufficient. An empty typed collection does not substitute for required.
- [] [RequestOneOf/Discriminator](../../reference/request/declaration.md) and custom
  preflight are covered when fields are mutually exclusive or input checks are complex.
- [] [DX and wire](../../reference/serialization/dto-output.md) are distinguished deliberately:
  enums, dates, naming, query arrays, textual booleans, and JSON strings have the required format.
- [] The [receiver](../../reference/serialization/receiver-output.md) is checked on input,
  manual DTO construction, and dispatch; cast/opaque-wrapper limitations are accounted for.
- [] Custom nested hydration preserves [scope](../../reference/dto/scope.md).

## Check protocol and environment <a id="section-4"></a>

- [] Credentials are placed through [auth/enrichment](../../reference/auth/README.md);
  scopes and secretKeys are centralized, and individual endpoint exceptions are explicit.
- [] [Quotas](../../reference/execution/rate-limit.md), [retry](../../reference/execution/retry.md),
  cache, and timeouts match API facts. Retry is permitted only when repetition is safe.
- [] When using [dependencies and composites](../../reference/request/composition.md),
  roles, errors, and the shared budget are checked; hooks preserve the prepared request format.
- [] [Files](../../reference/files/README.md) are checked separately from JSON, including
  stream ownership, download destination, and required archive drivers.
- [] Production/sandbox are separated by configuration. [Multiple services](../integration/multi-service.md)
  and [versions](../../reference/client/versioning.md) are introduced for actual contract differences.
- [] [Laravel](https://github.com/apisutra/laravel/blob/master/docs/en/guides/integration/laravel.md), if supported by the SDK, has a verified binding,
  configuration, and migration of old keys when existing consumers are present.

## Check results and diagnostics <a id="section-5"></a>

- [] Global and local error codes are separated; the mapper returns documented
  clientCode/status. appCode and providerTraceId are introduced only with a suitable contract.
- [] [Runtime metadata](../../reference/results/handles.md#section-11)
  is separate from static [catalogs](../../reference/client/catalogs.md).
- [] [Pagination](../../reference/execution/pagination.md) covers metadata, items,
  container/collection, and protective stopping when the operation is paged.
- [] [Continuation](../../reference/execution/continuation-state.md) covers explicit readiness,
  token, strict final hydration, limits, and failure; obtaining a token does not replace await.
- [] [Logs](../../reference/results/observability.md) contain sufficient safe diagnostics;
  original secrets and values do not enter automatic exports.

## Check tests and release <a id="section-6"></a>

The minimum is [requests, DTOs, and errors](../testing/unit.md) on local fixtures.
Record/playback helps with expensive, slow, limited, or unstable APIs.
For oneOf, use the supported RequestContractTestHelper. Pagination, await,
files, batch, and Laravel receive separate checks when actually used.

A [live test setup](../testing/live.md) is added as needed: gating, credentials,
cost, and mutable external state must be explicit. LiveTestGuard, LiveClientFactory,
fixture loader, cache, dumping, and Makefile belong to your SDK if you add them;
the core provides only the helpers listed in the reference.

The final check is [documentation and release](release.md). For an unsupported
operation or unknown condition, record its status and reason; do not mark it complete.
