<!-- languages --> <a href="hydration.md">English</a> · <a href="../../ru/development/hydration.md">Русский</a> <!-- /languages -->
# Input hydration execution <a id="section-1"></a>

Hydrator connects the model description to an input plan, processes properties, and
constructs the DTO. Attributes and external rules use the same executor. Public
contracts are in the [DTO reference](../reference/dto/README.md); this document
explains internal boundaries.

## Decision owners <a id="section-2"></a>

| Component | Responsibility |
| --- | --- |
| [MetadataCatalog](../../../src/Metadata/MetadataCatalog.php) | Neutral Reflection information about classes and property declaration scope |
| [RuleSetCompiler](../../../src/Serialization/Rules/RuleSetCompiler.php) | Conflicts, applicable rules, policies, and receiver; publication of a validated description graph |
| [HydrationPlanCompiler](../../../src/Serialization/Hydration/HydrationPlanCompiler.php) | Source/fallback, presence, normalization, defaults, transformation operation, and constructor slots |
| [HydrationPlan](../../../src/Serialization/Hydration/HydrationPlan.php) | Fields and argument recipes; bind creates attribute values for the current node |
| [HydrationFieldExecutor](../../../src/Serialization/Hydration/HydrationFieldExecutor.php) | Field stages, consumption, source location, and final validation |
| [HydrationObjectFactory](../../../src/Serialization/Hydration/HydrationObjectFactory.php) | Constructor arguments, one constructor call, constructorValue, and assignment of remaining fields |
| [HydrationScope](../../../src/Serialization/Rules/HydrationScope.php) | Active traversal, paths, and temporary handler context |

HydrationFieldPlan selects Cast, Shape, Identity, Nested, or Builtin. This is an
internal closed set of operations, with no registration of user-defined stages.
BuiltinHydrationCaster and HydrationTypeSelector choose native-type conversion for
the current value; compilation does not freeze a particular union branch.

## The plan and the current call <a id="section-3"></a>

The `:hydration-plan` key in AttributeMetadataCache stores plans in a WeakMap keyed
by CompiledDtoRules objects. Thus the same class with different rules gets different
plans even with a shared cache. Resetting descriptions or releasing the hydrator
releases dependent plans; the structural catalog receives no client policy.

A plan contains no DTOs, input data, handler instances, or profile results. Safe
declaration values are reused. An attribute with object arguments remains a
Reflection recipe executed for each node, including missing/null fields. After
binding, the live DTO profile is resolved; its policy and registry apply to the
current node. With metadata caching disabled, the plan is rebuilt, while validated
rule descriptions retain their own memoization.

The plan is published after all node attributes have been successfully materialized.
A recipe failure neither publishes a partial plan nor retains created arguments.
A failure during rebinding does not damage the completed recipe; the next call
retries materialization. The [value lifecycle](../reference/dto/lifecycle.md) is the
same with and without caching.

## Stage order <a id="section-4"></a>

1. Check DTO availability and resolve its declarations.
2. Normalize the input; call computed() on a Response DTO.
3. Bind the plan and materialize attribute arguments in property order.
4. Resolve the profile. For each field, select primary/fallback and determine
   Missing/Null/Present, then check required, forbidExplicitNull, and input shape.
5. Normalize empty strings, apply the appropriate default/provider, execute the
   selected operation, and perform final scalar/native checks.
6. Collect consumption/extras and arguments, call the constructor once, check
   constructorValue, and populate remaining properties.

Missing and null are distinguished before defaults. Providers and custom casts mark
the provenance boundary; paths/candidates and safe error context are preserved.
PHP constructs constructor-default objects only when the argument is omitted.

## Distinct nesting contracts <a id="section-5"></a>

RuleValueProcessor executes ValueShape; NestedValueProcessor preserves attribute-list
provenance when rule capabilities are enabled. LegacyNestedHydrator executes the
legacy Nested operation without replacing it with a strict list shape. Differences
in keys, variants, skip/error, itemCast, and source consumption are intentional.

Add new transformation rules to the relevant handler. Hydrator retains responsibility
for root scope, source normalization, and phase orchestration. ObjectFactory must not
repeat input conversion or write to an already initialized readonly field.
