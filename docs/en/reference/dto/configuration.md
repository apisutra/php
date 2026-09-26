<!-- languages --> <a href="configuration.md">English</a> · <a href="../../../ru/reference/dto/configuration.md">Русский</a> <!-- /languages -->
# Shared hydration configuration <a id="section-1"></a>

`HydrationConfig` combines input policy, optional external rules and a custom hydrator. Describe fields
on your own models with [attributes](declarations.md), and on third-party models
with [HydrationRules](field-rules.md). No mandatory list of child DTOs is required.

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;

$hydration = new HydrationConfig(policy: new RulePolicy(
    scalars: ScalarPolicy::Strict,
    naming: NamingStrategy::SnakeCase,
));
$config = new ClientConfig(baseUrl: 'https://api.example.test', hydration: $hydration);
$hydrator = Hydrator::forConfig($hydration);
```

Constructor: `HydrationConfig(?RulePolicy $policy = null, ?HydrationRules $rules = null, ?DtoHydratorInterface $hydrator = null, bool $jsonShapeValidation = true)`.
The object is immutable; a null policy retains core defaults rather than enabling Strict.
Input naming is independent of `ClientConfig::namingStrategy`, which names outgoing
request fields. An empty block differs from an absent block in
[items-only pagination](../execution/pagination.md): it explicitly enables itemsType processing.

`$config->with()` preserves the block; `with(hydration: null)` removes it only from
the copy. Attributes still work when entering a DTO; global `Hydrator::default()`
and `DTO::from()` do not receive other clients' configuration. For a standalone
external rule set, `Hydrator::forRules($rules)` remains available as a delegate to forConfig.

## Priorities <a id="section-2"></a>

| Source | Order from lowest to highest |
| --- | --- |
| Without a model profile | Core defaults → Config.policy → Rules.defaults → DtoRules.policy → FieldRule.policy |
| DtoHydrationProfile | Core defaults → Config.policy → complete profile policy for naming/date/empty-string |
| DtoHydrate | Explicit components of the nearest attribute override the applicable base |
| External rules and a profile | Existing Rules.defaults are excluded; explicit DtoRules for the same class conflicts |
| ScalarPolicy | The older profile does not declare scalars; shared Config Strict is retained, and DtoHydrate.scalars may replace it |

Nullable RulePolicy and DtoHydrate components mean “unset”. None/Keep and the complete
profile's date defaults are actual values. RulePolicy.dateTime replaces the entire
component; individual DtoHydrate parameters override only their corresponding settings.
Field-level From/Map/Nested.from, DateTimeFrom, and EmptyStringAsNull retain their
priorities. Cast still bypasses empty-string normalization.

Transformation order: field Cast / external handler → profile cast by type →
applicable policy cast → built-in transformation. A profile cast does not erase
shared handlers for other types. A cast does not bypass the final Strict check.
Nested takes priority over Cast and follows its declared object/list behavior.

Shared Config Strict + DtoHydrate with only naming remains Strict.
Rules.defaults Strict does not apply to a model declaring DtoHydrate; without another
scalar policy, that model uses Legacy. Set Config.policy for a shared Strict policy.

## Where the block applies <a id="section-3"></a>

Returns, async, nesting, collections, composite, Ready, and repeated awaitAs use the
same client hydrator. The HTTP cache stores the response; the current configuration
builds the DTO. Casts/providers receive current rules through the [context](scope.md).
A non-null response handler result, RawResponse, and Download still bypass hydration.

The client serializer uses the same receiver declaration. `Serializer` always builds
the wire representation; `DtoSerializer` without config and ordinary toArray() retain
the receiver as a field. See [outgoing projection](../serialization/receiver-output.md).

The optional hydrator is described in [custom DTO hydration](hydrators.md); default null preserves native hydration.

## JSON container validation <a id="section-4"></a>

`jsonShapeValidation` defaults to **true**, including when the client has no explicit
HydrationConfig. The standard response decoder preserves JSON object/array identity
for the DTO traversal: root, nested objects, typed pagination items and built-in
continuation. [Shape rules and local exceptions](shapes.md#section-3) describe the
accepted inputs. Ordinary `array` without a shape declaration is not implicitly a list.

To omit the additional shape metadata and its processing for one client:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    hydration: new HydrationConfig(jsonShapeValidation: false),
);
```

This is one client-wide setting, independent of ScalarPolicy. DTO attributes,
profiles and external field rules do not override it. Separate clients may use
different modes concurrently. `ClientConfig::with()` preserves the hydration block;
removing it restores the default. Public json()/jsonStrict() retain their ordinary
decoding behavior in either mode. Already-decoded PHP values cannot recover lost
JSON identity, even with this setting enabled.

| Input, without local normalization permissions | Enabled | Disabled |
| --- | --- | --- |
| `{}` for a ListShape | Rejected | Accepted as an empty PHP list |
| `{"0":"a","1":"b"}` for a ListShape | Rejected | Indistinguishable from a PHP list |
| `{"name":"a"}` for a ListShape | Rejected | Rejected by PHP key shape |
| `[]` for a DTO with defaults | Rejected | Accepted as an empty set of fields |
| `["a"]` for a DTO | Rejected | Rejected; values are not silently discarded |

Disabling skips source-shape metadata processing; ordinary decoding, hydration,
required/null checks, declared shapes and final PHP-type checks still run.
It does not reproduce every behavior of 0.1.1: nonempty lists cannot produce DTOs
from defaults in either mode. Conversely, a numeric-key JSON object accepted as a
DTO with source metadata may be rejected without it because its PHP value looks
like a nonempty list.

`inputShape` declares the expected input; `emptyListAsObject` permits only an empty
list for one DTO, and `normalizeKeys` permits map-to-list conversion. They remain
useful with validation enabled and do not re-enable the mechanism when disabled.
Use a local permission when just one provider field needs normalization.

Enabled validation adds decoding work and temporary memory; cost depends on response
size and structure. Many empty objects or objects with consecutive numeric keys can
require substantial shape metadata. JSON decoding processes the whole response;
its byte size does not predict peak PHP memory use. There is no fixed minimum
`memory_limit`: measure peak usage with representative responses and concurrency.
For large paginated responses, smaller pages and lower concurrency reduce peak memory.

Disabled mode omits that additional mechanism throughout the client, including
built-in continuation. Neither mode adds metadata processing to RawResponse,
downloads, terminal response handlers or public json()/jsonStrict().
