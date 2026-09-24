<!-- languages --> <a href="agent.md">English</a> · <a href="../../ru/start/agent.md">Русский</a> <!-- /languages -->
# For agents using ApiSutra <a id="section-1"></a>

Scope: external API SDKs, applications using an existing SDK, and extensions through
public casts, providers, hooks, transport, or result classes. Changes to ApiSutra itself
have a [separate entry point](https://github.com/apisutra/php/blob/master/.agents/README.md).

## Choosing a solution <a id="section-2"></a>

The [capability map](agent-capabilities.md) connects tasks to mechanisms, conditions
of use, and the documents that own their contracts. Select sections for the task;
their order does not prescribe a work sequence. An available capability need not be enabled.

Base the solution on the user's goal, the existing SDK, the installed ApiSutra version,
and the confirmed external API contract. Examples illustrate possible solutions;
the host project's rules determine structure and style.

## Responsibilities <a id="section-3"></a>

| Layer | Responsibility |
| --- | --- |
| ApiSutra | General mechanisms for requests, transformations, execution, results, and extensions |
| Provider SDK | Addresses, operations, auth scheme, models, error formats, and specifics of the API |
| Application | Credentials, environment, storage, use cases, and business decisions |

A custom cast, hook, or result class in an SDK uses the public API and does not itself
justify changing the core. Assess a capability against its contract and the scenario,
rather than requiring an identical example in the documentation.

## Selection criteria <a id="section-4"></a>

- **Existing structure.** Follow the SDK's established organization. A base DTO,
  profile, factory, catalog, or additional layer needs a concrete benefit;
  a teaching example does not require creating all of them.
- **API evidence.** Derive types, nullability, pagination, retries, and auth from
  the specification and observed responses. Identify assumptions separately;
  unknown provider behavior does not become an SDK guarantee.
- **Suitable mechanism.** Declarations suit standard data mapping; use a custom
  handler for behavior beyond their scope. Detailed precedence and limitations
  belong in the [reference](../reference/README.md).
- **Compatibility.** Signatures must match the installed package version.
  Changes to DTO representation, error policy, or the way requests are sent
  must account for existing SDK consumers.
- **Verification.** Choose checks for the change: for example, HTTP request shape,
  response transformation, or error handling. Use the consumer project's tools
  and commands; its tests do not need ApiSutra's internal test environment.
  Distinguish reproduced behavior, conclusions drawn from code, and assumptions.

## Further reading <a id="section-5"></a>

Task guides: [create an SDK](create-sdk.md), [add an operation](add-operation.md),
[describe DTOs](describe-dto.md), [use a client](use-sdk.md), [diagnose failures](diagnose.md).

Overview examples: [client](../guides/client/showcase.md), [DTO](../guides/dto/showcase.md),
[example SDK](../examples/sdk.md). [All user documentation](../README.md).
