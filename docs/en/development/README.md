<!-- languages --> <a href="README.md">English</a> · <a href="../../ru/development/README.md">Русский</a> <!-- /languages -->
# Developing ApiSutra <a id="section-1"></a>

Technical guidance for changing `apisutra/php` itself: core, tests, dependencies,
documentation, and CI. Instructions for AI agents are separate in
[.agents/README.md](../../../.agents/README.md).
For external API SDK development, use the [user task guide](../start/create-sdk.md).
Using public casts, hooks, and extensions also belongs to package usage.

## Get started <a id="section-2"></a>

1. Read the [architecture](architecture.md) and the relevant mechanism document below.
2. Compare expected behavior with the [public reference](../reference/README.md),
   current source, and tests. Clarify task scope and compatibility.

When handing a task to an agent, state the intended outcome, observed scenario,
constraints, and verification method. The agent selects applicable rules in `.agents/`;
external SDK work has a [user entry point](../start/agent.md).

## Prepare a checkout <a id="section-3"></a>

Requires Git, PHP 8.4+, and Composer. From the repository root:

```bash
composer install
composer test
```

Ordinary tests use local dependencies and need neither an external application nor a
database. Documentation checks require Python 3.9+; Laravel and Redis have separate
test environments. Commands and prerequisites are in [testing](testing.md).

## Select the area to change <a id="section-4"></a>

| Task | Read |
| --- | --- |
| Understand module relationships, clients, and extensions | [Architecture](architecture.md) |
| Change individual request stages | [Pipeline](pipeline.md) |
| Change pagination, composite, batch/pool, or continuation | [Execution flows](execution.md) |
| Change error classification and delivery | [Errors](error-handling.md) |
| Change caching, retry, or quotas | [Mechanism relationships](caching-retry.md) |
| Change DTO output plans, query/body, or receivers | [Serialization execution](serialization.md) |
| Change request field placement, HTTP adapters, or transformation assembly | [Request plans and HTTP boundaries](request-serialization.md) |
| Change input plans, field stages, or DTO construction | [Hydration execution](hydration.md) |
| Change attribute reading and metadata caching | [Attribute internals](attributes.md) |
| Add checks or test environments, or change CI | [Testing](testing.md) |
| Change documentation, examples, or distribution contents | [Documentation maintenance](documentation.md) |

These documents explain core internals. Complete user guarantees belong to the
[reference](../reference/README.md); listing an internal helper here does not make
it a public extension point.

## Make the change <a id="section-5"></a>

1. Record observed and expected behavior. General mechanisms remain in ApiSutra;
   API-specific behavior belongs to the provider SDK.
2. Change the affected mechanism, preserving its relationships and public contracts.
   When changing behavior, check the relevant scenarios, including errors.
3. Update the document that owns the changed contract and dependent examples.
   For compatibility changes, explain the required consumer actions in the release notes.
4. Run applicable [checks](testing.md). Report results, verification limits, and
   consequences for users in the change description.

Contribution descriptions follow [CONTRIBUTING](../../../CONTRIBUTING.md).

## Documentation distribution <a id="section-6"></a>

User README files, guides, reference pages, and examples ship in Git/Composer archives.
`docs/*/development/`, `CONTRIBUTING.md`, `.agents/`, and test environments
are available in checkouts and excluded from archives. User pages therefore link to
development documentation using full repository URLs.

`composer check-package` checks both archives' contents, installation without dev
dependencies, and standalone smoke tests. For working-tree changes, follow the
procedure in [testing](testing.md#section-2).
