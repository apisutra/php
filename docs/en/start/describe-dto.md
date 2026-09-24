<!-- languages --> <a href="describe-dto.md">English</a> · <a href="../../ru/start/describe-dto.md">Русский</a> <!-- /languages -->
# Describe DTOs <a id="section-1"></a>

Start with data and requirements, then choose a declaration style. PHPDoc `list<int>` alone does not validate array elements during hydration.

[DTO capabilities in one example](../guides/dto/showcase.md) demonstrates techniques from individual field attributes to lists, variants, extras, and serialization.

## Choose an approach <a id="section-2"></a>

| Condition | Approach |
| --- | --- |
| Models must remain ordinary PHP classes | [External rules](../guides/dto/plain-models.md) |
| The SDK owns the model and declarations fit naturally on properties | [DTO attributes](../guides/dto/attribute-models.md) |
| One class needs different mapping/strict/extras for different clients | [Client rule set](../reference/dto/field-rules.md) |

Combining approaches has explicit [conflict restrictions](../reference/dto/field-rules.md). Do not add attributes to an external model merely to attach a rule set.

Your own models can be fully described with [attributes](../reference/dto/declarations.md); [HydrationConfig](../reference/dto/configuration.md) defines the shared policy.

## Declaration order <a id="section-3"></a>

1. Establish [names and paths](../reference/dto/profiles.md), including fallback.
2. Decide [presence, null, and defaults](../reference/dto/defaults.md).
3. Define [object and list shapes](../reference/dto/shapes.md), [variants](../reference/dto/variants.md), and [strict scalars](../reference/dto/scalars.md).
4. Add casts only for necessary transformations. Nested hydration inside a custom handler uses [HydrationContext](../reference/dto/scope.md).
5. If unknown fields must be preserved, declare a [receiver](../reference/dto/extras.md), such as `_extra`. This is a configurable property, not a reserved response key.
6. Check the [output representation](../reference/serialization/README.md). Application `toArray()` output and serialization in client requests serve different purposes.

## Verify the result <a id="section-4"></a>

Using real anonymized fixtures, test an ordinary value, missing, null, a wrong type, an empty object/list, and unknown fields. For nested values, check the [error path](../reference/dto/diagnostics.md). Separately test manual DTO construction and sending: the receiver is excluded by the Extras attribute or an external class declaration.

[Executable examples](../examples/README.md) provide source files for both approaches.
