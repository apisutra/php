<!-- languages --> <a href="README.md">English</a> · <a href="../ru/README.md">Русский</a> <!-- /languages -->
# ApiSutra documentation <a id="section-1"></a>

[Your own DTOs: attributes and shared policy](reference/dto/declarations.md).

The guides and reference serve package users: authors of external API SDKs and
application developers using those SDKs. Development of ApiSutra itself has a
separate section below.

- [OAuth2: ready-made grants, PKCE, refresh and storage](reference/auth/oauth2.md).

## Start with a task <a id="section-2"></a>

- [Quickstart](guides/quickstart.md) — an executable example without network access.
- [Create an SDK](start/create-sdk.md).
- [Add an operation](start/add-operation.md).
- [Describe DTOs](start/describe-dto.md).
- [Explore DTO capabilities in one example](guides/dto/showcase.md).
- [Explore client construction and configuration](guides/client/showcase.md).
- [Add custom methods to an SDK result](guides/recipes/custom-result.md).
- [Upload, download, and open an archive](guides/recipes/files.md) — runnable without network access.
- [Use an existing SDK](start/use-sdk.md).
- [Typed async results and chains](reference/results/promises.md).
- [Run HTTP calls concurrently with sendAsync](reference/execution/transport.md#section-2) · [Limit batch/pool concurrency](reference/execution/batch-pool.md).
- [Process a large pool incrementally without retaining results](reference/execution/pool-consumption.md).
- [Set up an existing SDK in Laravel](https://github.com/apisutra/laravel/blob/master/docs/en/guides/integration/laravel.md) · [Add Laravel support to your SDK](https://github.com/apisutra/laravel/blob/master/docs/en/guides/sdk/laravel.md).
- [Configure the message language](reference/client/localization.md).
- [Diagnose a problem](start/diagnose.md).

[Entry points](start/README.md) help you choose a task guide.
For AI agents: [using the package](start/agent.md) and the
[capability map by task](start/agent-capabilities.md).

## Find details <a id="section-3"></a>

| Section | Purpose |
| --- | --- |
| [Guides](guides/README.md) | Complete a task with an example and verify the result |
| [Reference](reference/README.md) | Find exact API rules, precedence, and limitations |
| [Glossary](glossary/README.md) | Understand a term and find its contract |
| [Examples](examples/README.md) | Run published code and explore the SDK layout |

## Develop ApiSutra <a id="section-4"></a>

The [development guide](https://github.com/apisutra/php/blob/master/docs/en/development/README.md)
covers checkout setup, architecture, source code, and package checks.
For AI agents: [ApiSutra development rules](https://github.com/apisutra/php/blob/master/.agents/README.md).
This section concerns changes to ApiSutra itself. Using supported casts, hooks,
and extensions belongs to the user task guides above.
