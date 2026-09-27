<!-- languages --> <a href="CHANGELOG.md">English</a> · <a href="docs/ru/changelog.md">Русский</a> <!-- /languages -->
# Changelog <a id="section-1"></a>

## 0.3.1

- Update the runnable Records SDK to use a shared DtoVariants declaration for list,
  single-field and root inputs, with a typed fallback preserving unknown attachments.
- Demonstrate webhook hydration from original JSON with the HTTP client's configuration,
  and dictionary shape validation before a custom cast. Compare diagnostics for seventeen
  invalid bodies through HTTP and the JSON entry point.
- Expand the English and Russian DTO catalog, SDK walkthrough and quickstart;
  improve attributed-property spacing in the DTO examples. No core runtime API changes.

## 0.3.0

- Add public `Hydrator::hydrateJson()` and `#[InputShape(ContainerShape::Object)]`
  for incoming JSON and container validation before custom casts.
- Add type-level `DtoVariants` and external `HydrationRules::withVariants()` for
  HTTP, webhook, nested fields, lists, pagination and continuation. Unknown variants
  can hydrate a typed fallback; free VariantsShape also supports single fields.
- Rename NestedDiscriminatorMode to DiscriminatorMode, NestedUnknownVariant to
  UnknownVariant, and the Serialization\Rules\InputShape enum to ContainerShape.
  Update imports and enum references; the new InputShape is a property attribute.
- Value discriminator tags now accept only string/int. Boolean, float, array and
  object values fail with `invalid_discriminator_type` rather than implicit conversion
  or unknown fallback. Existing list defaults remain KeepRaw; type declarations default to Error.
- Unify variant selection and target validation, preserve source shape and diagnostics,
  and add a runnable HTTP/polling/webhook example. No new dependencies.
- Preserve ContinuationConfigurationException for invalid finalType declarations,
  with the shared validation error retained as the cause.
- Keep abstract/interface contracts valid for ready handler/composite results.
  Composite objects already matching the declared type skip rehydration;
  other object sources and arrays still hydrate.
- Validate all map/fallback classes against a typed collection’s item type before
  item selection, and include the unavailable class in variant configuration errors.
  Nested retains default KeepRaw: known variants work in typed collections,
  while unknown raw items still fail collection validation.

## 0.2.0

- Preserve JSON object/list identity through DTO hydration, nested shapes, cached and
  async responses, pagination items and built-in continuation results.
- Add client-wide `HydrationConfig::jsonShapeValidation` (enabled by default).
  Disabling it skips additional source-shape metadata processing, including continuation;
  DTO attributes and field rules do not override this setting.
- Reject nonempty lists passed as DTO input instead of silently producing default-valued
  DTOs. This validation remains active when JSON shape validation is disabled.
  No setting fully restores 0.1.1 behavior: a response such as `["a"]` for a DTO
  with defaults is rejected instead of silently discarding its values.
- Tighten shape validation by default: JSON objects (including `{}` and numeric-key objects)
  no longer satisfy strict lists; JSON arrays no longer satisfy DTO object inputs.
  Shape failures report the field path instead of silently accepting the wrong form.
- Add local `emptyListAsObject` to DtoShape, ValueShape::dto and Returns for providers
  that use `[]` for an empty object. Required fields still apply. Custom transformation
  boundaries and public decoded value types are unchanged; already-decoded PHP inputs
  cannot recover lost JSON identity.

## 0.1.2

- Expanded the runnable Records SDK with six related DTOs, typed collections, enum and
  discriminator variants, a bidirectional cast, inline Base64 and nested extra fields.
- Demonstrated recursive serialization, immutable copies, standalone hydration,
  missing/null/default rules and twelve invalid responses with field-level diagnostics.
- Updated the English and Russian walkthroughs and installation checks. No core runtime API changes.

## 0.1.1

- Expanded the English and Russian capability maps with grouped features and reference links.
- Added client configuration and result selection examples, clarified async and pagination,
  and linked the detailed DTO example.
- Refined overview navigation, badges and declarative SDK positioning. No runtime changes.

## 0.1.0

Initial release of the framework-independent PHP 8.4+ SDK toolkit.

- Declarative clients and requests, validation, resources and extension hooks.
- DTO hydration and serialization: attributes, external rules, nested models, collections,
  custom hydrators and explicit handling of unknown fields.
- Synchronous calls and concurrent async execution with typed promises, cancellation,
  batch/pool and incremental consumption with bounded result storage.
- Page/offset/cursor pagination, lazy item iteration and concurrent collection of
  independent pages with a shared deadline.
- Authentication strategies and OAuth2 Client Credentials / Authorization Code with
  PKCE S256, automatic refresh and persistable token/authorization-attempt snapshots.
- Safe retries, timeouts, execution budgets, response caching, quotas and Retry-After
  cooldown; optional atomic Redis backends for shared quotas and cooldown.
- File uploads/downloads, streamed payloads, composite requests and continuation polling.
- Structured results and errors, correlated traces, debug snapshots, secret masking,
  execution observation and English/Russian messages.
- Mock transports, strict fake sessions, recording/playback, client/request/DTO generation,
  and runnable examples including one Records SDK for PHP and Laravel.

Laravel integration is provided by [apisutra/laravel](https://github.com/apisutra/laravel).
See the [capability map](README.md#section-5) and [documentation](docs/en/README.md).
