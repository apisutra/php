<!-- languages --> <a href="request-serialization.md">English</a> · <a href="../../ru/development/request-serialization.md">Русский</a> <!-- /languages -->
# Request plans and HTTP boundaries <a id="section-1"></a>

[Serializer](../../../src/Serialization/Serializer.php) connects value transformation to
HTTP representation. SDK-author declarations and settings are described in
[request serialization](../reference/serialization/request-parts.md).

## Dependency assembly <a id="section-2"></a>

AbstractClient creates AttributeMetadataCache and Hydrator using the client's
HydrationConfig. Internal `Serializer::withDescriptions()` receives the assembled
RuleSetCompiler. Its neutral MetadataCatalog is also used by request and wire DTO
plans; external rules are not recompiled for each consumer.

Standalone constructors retain their existing arguments, including named calls.
Explicit configuration is validated when the facade is created. Internal output
consumers are created on first access; this does not defer errors in a supplied rule
set. Standalone use requires neither a container nor HTTP context. Unconfigured DX
retains the receiver as an ordinary field, while wire output discovers and excludes
an attribute-declared receiver.

## Field placement <a id="section-3"></a>

| Component | Decision |
| --- | --- |
| [RequestPartsPlanCompiler](../../../src/Serialization/Plan/RequestPartsPlanCompiler.php) | Reads HTTP declarations from the shared catalog, normalizes field destinations and RequestDefaults, and validates BodyRoot |
| [RequestPartsPlan](../../../src/Serialization/Plan/RequestPartsPlan.php) | Stores fields, root, and the unmapped rule; materializes object arguments per call |
| [RequestFieldPlan](../../../src/Serialization/Plan/RequestFieldPlan.php) | Contains destination, names/formats, and SerializationValuePlan; accounts for placeholders in the current URL |
| [RequestPartsCollector](../../../src/Serialization/RequestPartsCollector.php) | Reads values with pagination overrides, excludes receivers, applies the current policy, and builds RequestPartsBag |
| [RequestUrlBuilder](../../../src/Serialization/RequestUrlBuilder.php) | Substitutes path values and encodes query |
| [FilePayloadPreparer](../../../src/Serialization/FilePayloadPreparer.php) | Builds JSON, multipart, binary/base64, and preserves stream ownership rules |

The request-parts plan is separate from the input DTO plan. The `:request-parts-plan`
cache stores only declarations, enum/scalar parameters, and Reflection recipes. It
fixes neither HTTP method nor URL, pagination overrides, or request values. The method
determines Convention on each call; explicit RequestDefaults takes precedence.

Ignore, static, and nonpublic fields are skipped. The remaining order is BodyRoot →
File → Header → explicit or implicit Path → Body → Query → unmapped. URL placeholders
take precedence over Body/Query without changing File/Header destinations. BodyRoot
conflicts are checked separately, including placeholders in the specific URL and
default placement in the body.

All object attribute arguments are materialized before values are read, including
skipped and null fields. An argument failure leaves no live objects in the cache.
Query/body use the shared [value executor](serialization.md); Header/Path transform
enums, while File builds FileInput. These parts do not invoke Cast merely because
it is present on the property.

## HTTP preparation order <a id="section-4"></a>

Serializer collects parts, then applies request enrichers and the continuation-mode
applicator. It then builds the URL, prepares the payload and file-transfer options.
HTTP caching, auth, retry, rate limiting, and transport remain in the [pipeline](pipeline.md).
Preparation errors prevent sending the request; they do not undo effects of custom
handlers that have already run.

## Response entry points <a id="section-5"></a>

Returns, collections, pagination, composite, and final continuation hydration use the
client's hydrator. Repeated awaitAs transforms the saved Ready payload through the
same service. Ready conversion errors remain errors rather than becoming Pending.

`hydration: null` keeps items-only pagination raw even with DTO attributes. Explicit
HydrationConfig enables item typing. The HTTP cache stores responses: each client
transforms them using its own rules. Non-null response handler results, RawResponse,
and Download retain separate branches without standard hydration.

## Checks <a id="section-6"></a>

[RequestPartsPlanTest](../../../tests/Unit/Serialization/RequestPartsPlanTest.php) checks
method and URL changes with a shared plan, part precedence, errors, argument/cast
traces, and object release. [AttributeHydrationEntriesTest](../../../tests/Unit/Serialization/AttributeHydrationEntriesTest.php)
and [ExternalHydrationEntriesTest](../../../tests/Unit/Serialization/ExternalHydrationEntriesTest.php)
cover response entry points; file, URL, enricher, and continuation tests check adapters.
