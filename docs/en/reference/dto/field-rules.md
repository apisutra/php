<!-- languages --> <a href="field-rules.md">English</a> · <a href="../../../ru/reference/dto/field-rules.md">Русский</a> <!-- /languages -->
# External rules and declaration conflicts <a id="section-1"></a>

## HydrationRules <a id="section-2"></a>

`ClientConfig::hydration` is `?HydrationConfig`, defaulting to null. The client passes
the rules to its hydrator and serializer. They apply to Returns, pagination, composite,
and await; the declared receiver is excluded from client requests. Copying with
`with()` preserves the rules; explicit null disables them in the new copy.

The [practical example](../../guides/dto/plain-models.md) shows how to build a rule set.
`ClientConfig::casts` concerns outgoing transformation; the hydrator does not read its registry.

## Rules and configuration validation <a id="section-3"></a>

All public descriptors live in `ApiSutra\Serialization\Rules`.
Builder methods return a new object. A class is registered by its exact name;
its schema does not vary with its path in the graph.

| API | Purpose |
| --- | --- |
| `HydrationRules::create(?RulePolicy $defaults = null)` | Shared defaults for the rule set |
| `withDto(string $class, DtoRules $rules)` | Rules for one class; duplicates forbidden |
| `defaults()`, `rulesFor(string $class)` | Read defaults and class rules |
| `DtoRules::create(?RulePolicy $policy = null)` | Class policy |
| `field(string $property, FieldRule $rule)` | One rule for a physical property |
| `extras(string $property)` | One property for the source remainder |
| `FieldRule::create()->from(string $path, string ...$fallback)` | Primary and fallback dot paths; a null primary counts as found |
| `shape(ValueShape $shape)` | Transform a nested `shape` |
| `cast(HandlerSpec $cast, ?ValueShape $result = null)` | Obtain a value from a `cast`; result only validates it |
| `noTransform()` | Explicitly disable transformation; native type is still validated |
| `constructorValue(bool $allowMissing = false)` | [Validate a constructor value without writing again](constructor-values.md) |
| `required()`, `forbidExplicitNull()` | Require key presence and forbid original null |
| `inputShape(InputShape $shape)` | Validate Object/List before `cast` or `noTransform` |
| `default(DefaultSpec $default)`, `policy(RulePolicy $policy)` | Field default and policy |

Duplicate fields/receivers, repeated from/default/policy groups, or multiple
transformations (`shape`, `cast`, `noTransform`) produce `ConfigurationException`.
The hydrator compiles rules at `forRules()` or client construction, checking classes,
properties, receivers, references, and discriminator maps. DTO constructors do not run then.

`FieldRule` conflicts with input attributes on **the same property**: From, Map, Nested,
Cast, DateTimeFrom, EmptyStringAsNull, DefaultValue, RequiredInput, ForbidExplicitNull,
ConstructorValue, and Shape. Extras is checked separately: a class cannot declare
both attribute and external receivers. Attributes on neighboring fields still work.
To, DateTimeTo, and unrelated attributes do not conflict. `DtoRules` conflicts with
local or inherited DtoHydrate/DtoHydrationProfile.

## Policy priorities <a id="section-4"></a>

`RulePolicy` accepts nullable `scalars`, `emptyString`, `naming`, `dateTime`, and a
`casts` array of `PHP type name => HandlerSpec`. Null means “unset”. Field policy
overrides class policy, then rule-set defaults, then shared HydrationConfig.policy
and core defaults. See the [complete table including profiles](configuration.md#section-2).
`dateTime` is a complete `DateTimeHydrationPolicy`; its individual fields are not merged.

Transformation priority: field `cast` → class `casts` → rule-set `casts` → built-in
transformations. Field-level `RulePolicy::casts` is forbidden: use `cast()`.
`noTransform()` disables this selection. A profiled class not registered through
`DtoRules` fully retains its profile rules and ignores rule-set defaults.
Policy is selected anew on entering a child DTO: a Legacy parent does not cancel a Strict child.

## Complete application path <a id="section-5"></a>

Connect the rule set as `new HydrationConfig(rules: $rules)`.
Client rules apply to ordinary Returns, the promise API, pagination, composite,
await, and repeated awaitAs. The HTTP cache stores the original response: the current
rule set builds the DTO. A non-null response handler result, RawResponse, and Download
bypass ordinary hydration.

`ValueShape::dto($class, emptyListAsObject: true)` permits an empty JSON list only
at that node. An explicit `inputShape(Object)` still checks raw input before this
conversion. By default, standard HTTP decoding preserves JSON kinds (see [client mode](configuration.md#section-4)); standalone PHP arrays
retain their existing ambiguity. [Examples and boundaries](shapes.md#section-3).

`Hydrator::forRules($rules)` explicitly connects the same rules for standalone use.
`Hydrator::default()` and `DTO::from()` do not inherit client rules. Creating a rule
set does not change existing objects' semantics. For recursion in a custom `cast`/provider,
use [scope](scope.md), not default().

[Strict](scalars.md) · [Shapes](shapes.md) · [Defaults](defaults.md) ·
[Extras](extras.md) · [Diagnostics](diagnostics.md).
