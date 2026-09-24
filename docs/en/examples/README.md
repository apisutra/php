<!-- languages --> <a href="README.md">English</a> · <a href="../../ru/examples/README.md">Русский</a> <!-- /languages -->
# Executable examples <a id="section-1"></a>

Examples ship with the package. They require Composer autoload; local fixtures allow them to run without network access or credentials.

| Example | Demonstrates |
| --- | --- |
| [Async results](../../example/async-results/run.php) | Typed single/batch/pool/consume promises, nested then, FAILED versus rejection and otherwise, without network access |
| [Custom DTO factories and lazy items](../../example/dto-hydrator/run.php) | Private constructors, nested DTOs, scoped hydration, explicit serialization and a failed page without data loss |
| [OAuth2](../../example/oauth2/run.php) | Client Credentials reuse, async code exchange, invalid callback, refresh after 401 and saving rotated tokens, without network access |
| [Operation exceptions](result-errors.md) | Runtime Returns validation, per-operation messages, one exception factory, and fallback |
| [Message language](localization.md) | Two independent clients, a DTO error, an SDK catalog, and literal strings |
| [Records SDK](sdk.md) | Configuration, transport, resource, request, DTO, and an HTTP error |
| [Client capabilities](client-showcase.md) | Auth, timeouts, retry, quotas, cache, DTOs, diagnostics, and per-call options |
| [Custom result](custom-result.md) | SDK methods over ResolvedResultInterface, a factory, and preservation of standard errors |
| [DTO capabilities](dto-showcase.md) | Attributes and rules for one product, serialization, a Base64 field, defaults, and diagnostics |
| [Files and archives](files.md) | Multipart/binary/Base64 upload, download to a path or stream, and reading TAR |
| [Await an operation](continuation.md) | Pending/Ready, token, strict final data, and await caching |
| [DTO rules](hydration-rules.md) | Mapping, nested/each, a strict list, extras, and a scoped cast |

[Quickstart](../guides/quickstart.md) runs the Records SDK. The same SDK can be installed through Composer and [connected to Laravel](https://github.com/apisutra/laravel/blob/master/docs/en/guides/integration/laravel.md). All contracts are in the [reference](../reference/README.md).

Reference snippets explain individual API calls and may require an existing client or model. Start with the corresponding example's documentation for complete, reproducible code; classes are in separate files.
