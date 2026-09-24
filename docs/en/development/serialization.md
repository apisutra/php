<!-- languages --> <a href="serialization.md">English</a> · <a href="../../ru/development/serialization.md">Русский</a> <!-- /languages -->
# Output serialization execution <a id="section-1"></a>

Output uses a shared structural plan and shared value-conversion selection. DX,
explicit serialization, and HTTP policies are set at their entry points. The public
contract is in the [serialization reference](../reference/serialization/README.md).

## Decision owners <a id="section-2"></a>

| Component | Responsibility |
| --- | --- |
| [SerializationPlanCompiler](../../../src/Serialization/Plan/SerializationPlanCompiler.php) | Selects output declarations from MetadataCatalog, checks visibility, and publishes a completed plan |
| [SerializationPlan](../../../src/Serialization/Plan/SerializationPlan.php) | Stores field templates and Reflection recipes; materializes object arguments for the current node |
| [SerializationFieldPlan](../../../src/Serialization/Plan/SerializationFieldPlan.php) | Defines field reading and output name selection: To → Map → current naming policy |
| [SerializationValuePlan](../../../src/Serialization/Plan/SerializationValuePlan.php) | Stores declared-type candidates and the Cast description; selects a type for the current value |
| [DtoSerializer](../../../src/Serialization/DtoSerializer.php) | Binds a plan to policy/registry, protects the active branch, reads fields, skips null, and writes the result |
| [SerializationValueResolver](../../../src/Serialization/SerializationValueResolver.php) | Executes Cast/DTO/array/type/enum/date/object precedence for DTOs and query/body |
| [ReceiverOutput](../../../src/Serialization/Rules/ReceiverOutput.php) | Checks visible receivers before casts and builds a representation without receivers |

These plan types are internal. They are neither a new DSL nor a registration point
for user-defined stages; DTO declarations and public facades remain unchanged.

## Compilation and binding to a call <a id="section-3"></a>

The plan cache uses the same AttributeMetadataCache switch and a separate
`:serialization-plan` key. It contains no DTO or handler instances, results of
profile.policy()/casts(), or object arguments. Safe declaration values are normalized;
an argument containing `new` remains a Reflection recipe. This flat field description
neither resolves child classes nor contains configuration. The rule graph and receiver
still belong to RuleSetCompiler with a separate [publication boundary](attributes.md#section-4).

The first call reads properties in order and materializes declarations at the same
point as before. The plan is published after a successful pass. Subsequent calls
materialize all object arguments before reading the first value, including null and
fields that will be skipped. A recipe failure leaves no objects in the cache; the next
root call can retry execution. Changed profiles and intentionally shared handlers
retain their behavior.

PropertyTypeInspector resolves relative native names against the declaring class;
input, final checks, and Nested use the same rule. A plan stores resolved candidate
names without fixing the runtime branch. One field may contain different union
variants, DTOs, or arrays. Complex types that the existing PropertyTypeInspector
parses only on access retain that deferred boundary.

## Execution order <a id="section-4"></a>

| Entry point | Binding |
| --- | --- |
| serialize / toArray | Receiver discovery when configuration is enabled → profile → DTO guard → materialization → fields; child DTOs resolve their own profiles |
| serializeWithPolicy | Explicit policy/registry → DTO guard → materialization → fields with receiver checks; child DTOs inherit policy/registry |
| HTTP DTO | Serializer discovers receivers, resolves the wire profile, and calls serializeWithPolicy |
| Request query/body | [RequestPartsPlan](request-serialization.md) defines placement; the collector passes the value plan to the shared resolver |

A value is read immediately before its field is transformed: an earlier callback
may change the next field or a shared registry. The resolver is reused within the
node, but the selected handler is not cached in place of consulting the live registry.
Header/path/file retain their own adapters and do not get the full Cast/DTO path.

## Receiver validation and projection <a id="section-5"></a>

A separate receiver-presence check runs before the selected custom cast. It preserves
rejection before handler creation and invocation; object arguments may already have
been created when reading metadata. DX and explicit policy may differ in the order
of early errors; this is part of the existing contract.

For arrays, DTOs and enums are transformed first, then ReceiverOutput traverses the
completed representation. The traversals are deliberately separate: a late callback
can add a receiver to a previously visited plain object. Merging passes or caching
results by object ID would miss that change. Opaque objects retain their boundary;
the core does not mutate original plain objects. Repeated references in sibling
branches are allowed.

[Traversal state](architecture.md#section-13) and handler contexts share an underlying
design, but DTO, array, and receiver limits remain distinct.

## Verifying changes <a id="section-6"></a>

[SerializationPlanTest](../../../tests/Unit/Serialization/SerializationPlanTest.php) checks
live profiles, shared caching, unions, registry changes inside callbacks, projection
order, and argument release. Metadata isolation, context, receiver, request-part,
and cycle tests complement it. Matching JSON alone is insufficient: preserve traces
and exceptions, and measure cold/warm/cache-off separately from traversal instrumentation.

Cold-plan cost includes building field descriptions. For fields without declarations,
the shared reader skips separate lookups of each attribute; simple native types are
normalized without an intermediate candidate list. These reductions neither cache
call values nor change when object arguments are created.
