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

Constructor: `HydrationConfig(?RulePolicy $policy = null, ?HydrationRules $rules = null, ?DtoHydratorInterface $hydrator = null)`.
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
