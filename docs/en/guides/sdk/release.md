<!-- languages --> <a href="release.md">English</a> · <a href="../../../ru/guides/sdk/release.md">Русский</a> <!-- /languages -->
# SDK documentation and release <a id="section-1"></a>

The goal is for a consumer to install the SDK, create a client, call an operation,
and handle an error using the published instructions. Before release, complete [coverage](coverage.md).

## SDK documentation structure <a id="section-2"></a>

| Layer | Content |
| --- | --- |
| README | Purpose, requirements, installation, first call, task links |
| Guides | Real scenarios and verifiable examples |
| SDK reference | Operations, parameters, and provider-specific differences |
| Glossary, if needed | Short linked definitions of provider concepts |
| CONTRIBUTING/development | Changing the SDK itself, tests, architecture, and release |
| Migration and changelog | Compatibility changes and consumer actions |

Group files by task or resource. If a document grows, extract an independent scenario
or contract; do not split consecutive prose into numbered parts.
Architecture diagrams help SDK developers but must not be required reading for API users.
Give agents the shared user route.

## What to document for an operation <a id="section-3"></a>

State its purpose, public call, required input, result type, errors, and special modes.
The example must make configuration, transport, auth scope, and DTO preparation clear.
Record API version, sandbox, access restrictions, and charges from confirmed provider facts.

For pagination, state how to obtain pages and the overall limit. For long-running operations,
explain Ready/Pending, token, and await. For files, explain the destination and resource ownership.
Keep known limitations next to the relevant call.

Do not copy the general ApiSutra contract: link to the appropriate
[reference](../../reference/README.md) section and describe only your SDK's choices and specifics.

## Preparation sequence <a id="section-4"></a>

1. Record user tasks and supported operations.
2. Align terminology and resource, DTO, and enum names with the code.
3. Prepare one executable example; the README and test must use the same source.
4. Describe independent scenarios and link them to the documents owning their contracts.
5. Add SDK developer instructions separately, then check navigation and document sizes.

When changing an operation, update its example and documentation together.
A new concept gets a definition; new behavior gets a contract and a check.
Do not keep competing algorithms in the README, glossary, and reference.

## Release checks <a id="section-5"></a>

- Installation in a clean application succeeds with runtime dependencies; autoload finds the classes.
- The first example runs from the published package without hidden variables or bindings.
- Supported environments are checked: standalone when support is claimed, Laravel and special
  transports when the respective integration exists.
- Fake tests reproduce requests, DTOs, errors, and the complex scenarios used.
- Live tests run explicitly and do not turn ordinary installation into an API call.
- Links and anchors exist; examples work from the installed SDK without files from its repository.
- Repositories and archives contain no secrets; fixtures are anonymized.
- Version and migration reflect compatibility, especially strict, shape, and wire changes.

The [Records SDK](../../examples/sdk.md) shows a small set of README, sources,
Composer manifest, and fixtures. Install it and use it as a starting point with your
SDK's namespace and data. When shipping [Laravel integration](https://github.com/apisutra/laravel/blob/master/docs/en/guides/sdk/laravel.md), check discovery
from the installed package, defaults without publication, application overrides, and `config:cache`.
