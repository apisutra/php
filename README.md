<p align="center"><img src="docs/assets/apisutra-logo.png" alt="ApiSutra logo" width="233"></p>
<h1 align="center">ApiSutra</h1>
<h2 align="center">Declarative PHP SDK for external APIs</h2>

<p align="center">
  <a href="https://github.com/apisutra/php/actions/workflows/tests.yml"><img src="https://github.com/apisutra/php/actions/workflows/tests.yml/badge.svg?branch=master&amp;event=push" alt="Tests"></a>
  <a href="https://github.com/apisutra/php/actions/workflows/tests.yml?query=branch%3Amaster"><img src="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fapisutra%2Fphp%2Fbadges%2Ftest-count.json" alt="Test count"></a>
  <a href="https://github.com/apisutra/php/actions/workflows/docs.yml"><img src="https://github.com/apisutra/php/actions/workflows/docs.yml/badge.svg?branch=master&amp;event=push" alt="Docs CI"></a>
  <a href="docs/en/README.md"><img src="https://img.shields.io/badge/docs-multilingual-2563eb" alt="Multilingual documentation"></a>
  <a href="composer.json"><img src="https://img.shields.io/badge/PHP-8.4%2B-777BB4" alt="PHP 8.4+"></a>
  <a href="https://packagist.org/packages/apisutra/php"><img src="https://img.shields.io/packagist/v/apisutra/php" alt="Packagist"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-green" alt="MIT license"></a>
</p>

<p align="center">
<!-- languages --> <a href="README.md">English</a> · <a href="docs/ru/overview.md">Русский</a> <!-- /languages -->
</p>

ApiSutra is a PHP toolkit for external API SDKs: request and DTO declarations, authentication and execution policies, typed results and diagnostics. **PHP 8.4+.** Works standalone; [Laravel 13 integration](#section-6) is a separate package.

[Quickstart](docs/en/guides/quickstart.md) · [Capabilities](#section-5) · [Documentation](docs/en/README.md)

## Install <a id="section-7"></a>

```bash
composer require apisutra/php
```

### Run the example <a id="run-example"></a>

The included [Records SDK](docs/en/examples/sdk.md) uses mock responses: no API keys or network access needed.

```bash
php vendor/apisutra/php/docs/example/sdk/run.php
```

## From a request to a DTO <a id="section-1"></a>

These snippets use the [Records SDK](docs/example/sdk/src/DemoClient.php); imports are omitted, the API address and token are illustrative.

### Declare the operation <a id="section-3"></a>

```php
#[Get('/records/{id}')]
#[Retry(attempts: 3)]
#[Returns(GetRecordResponseDto::class, unwrap: 'data')]
final class GetRecordRequest extends AbstractRequest
{
    public function __construct(#[Path] public int $id) {}
}
```

The [request](docs/en/reference/request/declaration.md) declares its route, up to three attempts, and the response DTO.

### Describe the data <a id="section-4"></a>

A shortened version of the [response DTO](docs/example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php):

```php
final readonly class GetRecordResponseDto extends AbstractResponseDto
{
    public function __construct(
        #[From('record_id', fallback: ['id'])]
        public int $id,
        public string $title,
        #[From('created_at')]
        #[DateTimeFrom(format: DATE_ATOM)]
        public DateTimeImmutable $createdAt,
    ) {}
}
```

Explore a [detailed DTO example with attributes](docs/en/guides/dto/showcase.md#section-3): mapping, casts, nested DTOs, collections, extras and files. [Custom hydrators](docs/en/reference/dto/hydrators.md) are supported; `toArray()` and HTTP serialization have separate rules.

### Configure the client and send <a id="section-2"></a>

Only `baseUrl` is required in `ClientConfig`. Add the policies your integration needs, for example:

```php
$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    auth: new BearerAuthenticator('your-api-token'),
    timeout: 15,
    retry: new RetryConfig(attempts: 3, totalTimeoutMs: 30_000),
    hydration: new HydrationConfig(policy: new RulePolicy(scalars: ScalarPolicy::Strict)),
    throwOnErrors: false,
);

$client = new DemoClient($config, HttpTransport::createDefault());

$handle = $client->send($client->records()->get(7)->withTimeout(5));
/** @var GetRecordResponseDto $record */
$record = $handle->dataOrFail();
echo $record->createdAt->format('Y-m-d');
```

The client allows 15 seconds per HTTP attempt and 30 seconds overall; this request's `withTimeout(5)` sets 5 seconds per attempt. `$config->with(...)` creates a new configuration. [Other settings](docs/en/guides/client/showcase.md): cache, quotas, logging, serialization and extensions.

`send()` waits and returns a `ResultHandle`. With `throwOnErrors: false`, you can inspect failures before deciding how to handle them:

| Read the handle | Value and purpose |
| --- | --- |
| `dataOrFail()` | The declared DTO for application logic; throws on FAILED even with `throwOnErrors: false`. Other requests can return collections, arrays, scalars, text, null or `FileResponse`. |
| `resolved()` | `ResolvedResultInterface`: data, status, messages and mapped errors for application branching or UI. Inspect FAILED without throwing. |
| `raw()` | `ExecutionResult`: original HTTP response, errors, metadata, child results, trace/audit/debug for diagnostics and custom processing. It does not select an undecoded body. |

All three read the same execution without another HTTP call. [Result representations and errors →](docs/en/reference/results/handles.md)

## Independent calls and large datasets <a id="async-and-pagination"></a>

Start independent requests before waiting to overlap HTTP with the built-in Guzzle transport:

```php
$first = $client->sendAsync($client->records()->get(7));
$second = $client->sendAsync($client->records()->get(8));

$record7 = $first->wait()->dataOrFail();
$record8 = $second->wait()->dataOrFail();
```

`sendAsync()` returns a [typed Guzzle-compatible promise](docs/en/reference/results/promises.md). Await it; no worker or manual event-loop setup is needed.

Here `$request` is a paginated request bound to a client, and `$repository` is application storage:

```php
foreach ($request->paginate()->items() as $item) {
    $repository->save($item);
}
```

The [item stream](docs/en/reference/execution/pagination-items.md) loads pages sequentially without retaining the dataset; FAILED throws. For collection and concurrency, see [pagination](docs/en/reference/execution/pagination.md#section-21); for incremental processing of independent requests, see [pool `consume()`](docs/en/reference/execution/pool-consumption.md).

## Capability map <a id="section-5"></a>

### Organize an SDK <a id="capabilities-sdk"></a>

| Need | What ApiSutra provides |
| --- | --- |
| SDK structure | [Clients and nested resources](docs/en/reference/client/resources.md), [multiple services](docs/en/guides/integration/multi-service.md), [API versions](docs/en/reference/client/versioning.md), and [client discovery](docs/en/reference/client/discovery.md). |
| SDK catalogs | [Operation inventories](docs/en/reference/client/operation-inventory.md), [response DTO catalogs](docs/en/reference/client/response-dto-catalog.md), and [static provider reference data](docs/en/reference/client/catalogs.md): capabilities, tariffs, and dictionaries without HTTP. |

### Configure clients, transport, and authentication <a id="capabilities-client"></a>

| Need | What ApiSutra provides |
| --- | --- |
| Configuration | [Client settings, copies and per-call overrides](docs/en/reference/client/configuration.md), [optional container integration](docs/en/reference/client/construction.md), [multilingual messages with a per-client locale and custom translations](docs/en/reference/client/localization.md). |
| HTTP and async | [PSR-18/PSR-17 transport integration](docs/en/reference/execution/transport.md), synchronous `send()`, concurrent `sendAsync()`, cancellation; [typed Guzzle-compatible promises](docs/en/reference/results/promises.md) with `wait`/`then`/`otherwise`. Custom transports must support async explicitly. |
| Authentication and tokens | [API key, Bearer, Basic, HMAC and auth scopes](docs/en/reference/auth/strategies.md); [token caching, refresh after 401 and refresh locks](docs/en/reference/auth/tokens.md). Shared storage alone does not guarantee cross-process locking. |
| OAuth2 | [Client Credentials and Authorization Code with PKCE S256](docs/en/reference/auth/oauth2.md), state validation, automatic refresh, effective scopes, and export/restore of tokens and authorization attempts. Storage and cross-process rotation coordination belong to the application. |
| Credentials and destinations | [Credential enrichment and origin protection](docs/en/reference/auth/credentials.md), isolated authentication contexts; [complete and signed URLs](docs/en/reference/serialization/uri-query.md) without automatically forwarding client credentials. |

### Describe requests and outgoing data <a id="capabilities-requests"></a>

| Need | What ApiSutra provides |
| --- | --- |
| Request declarations | [HTTP attributes, path/query/header/body fields, oneOf and discriminator](docs/en/reference/request/declaration.md); [request validation before HTTP, custom preflight checks and explicit DTO validation](docs/en/reference/client/validation.md). `Validate` rules require Illuminate Validation. |
| Serialization | Separate [DTO `toArray()` rules](docs/en/reference/serialization/dto-output.md) and [HTTP naming, array and boolean formats](docs/en/reference/serialization/request-parts.md); dates, enums, JSON/forms, [JSON within a field](docs/en/reference/serialization/casts.md), [root bodies for JSON Patch/bulk](docs/en/reference/serialization/body.md). |
| Response formats | [DTOs with explicit `unwrap`, or `RawResponse`](docs/en/reference/attributes/response.md); [JSON arrays, scalars, null and text without a DTO](docs/en/reference/results/handles.md#section-3). `raw()` reads execution details; `RawResponse` selects an undecoded body. |
| Files and archives | [Streaming multipart/binary uploads and Base64](docs/en/reference/files/uploads.md), [DTO file fields](docs/en/guides/dto/showcase.md#section-7), [downloads to files or streams](docs/en/reference/files/downloads.md), [listing, reading and extracting archives](docs/en/reference/files/archives.md). Base64 materializes the contents. |

### Transform responses and DTOs <a id="capabilities-dto"></a>

| Need | What ApiSutra provides |
| --- | --- |
| Model declarations | [Attributes on ordinary and readonly classes](docs/en/reference/dto/declarations.md), optional base DTOs, [external rules without changing models](docs/en/reference/dto/field-rules.md), [inheritance and recursive models](docs/en/reference/dto/models.md). |
| Field mapping | [Input names, nested paths and fallbacks](docs/en/reference/dto/profiles.md); independent output names, hydration profiles and [shared policy](docs/en/reference/dto/configuration.md). |
| Field contracts | [Missing vs null, required presence, forbidden null, defaults and empty strings](docs/en/reference/dto/defaults.md); [checking constructor-assigned values](docs/en/reference/dto/constructor-values.md). |
| Types and precision | [Scalar conversions, opt-in Strict, unions and large integer IDs without lost digits](docs/en/reference/dto/scalars.md); enums, [date formats and time zones](docs/en/reference/dto/profiles.md). |
| Complex structures | [Nested DTOs and strict list shapes](docs/en/reference/dto/shapes.md), [typed collections](docs/en/reference/dto/collections.md), [item variants selected by discriminator](docs/en/reference/dto/variants.md). |
| Additional data | [Preserve unmapped input with `Extras`](docs/en/reference/dto/extras.md), retain it in `toArray()`, and [exclude the receiver from outgoing requests](docs/en/reference/serialization/receiver-output.md). |
| Custom transformations | [Input/output casts and nested transformations with context, including without HTTP](docs/en/reference/dto/scope.md), [computed values](docs/en/reference/dto/lifecycle.md); [custom hydrators with DI and native fallback](docs/en/reference/dto/hydrators.md). |

### Control execution, load, and caching <a id="capabilities-execution"></a>

| Need | What ApiSutra provides |
| --- | --- |
| Safe retries | [Retry policies, backoff, Retry-After and idempotency](docs/en/reference/execution/retry.md), request overrides, and replay checks for file operations. |
| Time limits | [Per-attempt timeouts, total execution budgets and shared deadlines](docs/en/reference/execution/deadlines.md) across retries, authentication and dependent calls. |
| Request quotas | [Joint client and operation quotas, waiting or refusal](docs/en/reference/execution/rate-limit.md); local accounting or an optional [atomic Redis backend](docs/en/reference/integrations/redis.md). |
| Server cooldown | [Coordinate Retry-After prohibitions after 429](docs/en/reference/execution/cooldown.md) by operation/group, origin and credentials; budget-aware waits or refusal, optional sharing across clients/processes. |
| Response caching | [PSR-16, TTL, per-call modes, clearing and SDK/credential isolation](docs/en/reference/execution/cache.md). HTTP caching and token storage have separate controls. |

### Coordinate calls and large datasets <a id="capabilities-multiple-calls"></a>

| Need | What ApiSutra provides |
| --- | --- |
| Batch and pool | [Sequential/concurrent batch, concurrent pool, concurrency limits and failure strategies](docs/en/reference/execution/batch-pool.md); collected results and child diagnostics. |
| Incremental processing | [Pool `consume()` / `consumeAsync()`](docs/en/reference/execution/pool-consumption.md): iterable input of unknown size, handlers and summary counters without retaining all results; optional stop on failure. |
| Pagination | [Page/offset/cursor schemas, typed items, DTO metadata containers and traversal guards](docs/en/reference/execution/pagination.md); lazy [pages/items](docs/en/reference/execution/pagination-items.md), ordered concurrent collection of independent pages and a shared deadline. Cursors stay sequential; concurrent `all()` needs `total`/`perPage`; aggregate string-key collisions fail explicitly. |
| Dependent operations | [Composite requests and dependencies between steps](docs/en/reference/request/composition.md), composed results and a shared execution budget. |
| Deferred provider results | [Pending/Ready criteria](docs/en/reference/execution/continuation-state.md), [continuation tokens, await and bounded polling](docs/en/reference/execution/continuation-await.md). This concerns provider operation readiness, separately from concurrent HTTP. |

### Read results and diagnose failures <a id="capabilities-results"></a>

| Need | What ApiSutra provides |
| --- | --- |
| Results and errors | [Handle, application view and full execution result](docs/en/reference/results/handles.md); [SUCCESS/PARTIAL/FAILED, result or exception delivery and provider error mapping](docs/en/reference/results/errors.md), [exception factories](docs/en/reference/results/exceptions.md) and [custom result methods](docs/en/guides/recipes/custom-result.md). |
| Tracing and diagnostics | [Sync/async call trees, correlated logs, audit, debug and secret masking](docs/en/reference/results/observability.md); machine reasons/stages and [DTO error paths with original source locations](docs/en/reference/dto/diagnostics.md). |
| Observation | [Safe execution snapshots](docs/en/reference/results/observation.md): operation, outcome, correlation, attempt counts and durations, optional attempt details and a diagnostic client label. Delivery belongs to the application or Laravel adapter. |

### Extend, test, and scaffold <a id="capabilities-extensions"></a>

| Need | What ApiSutra provides |
| --- | --- |
| Extension points | [Lifecycle hooks](docs/en/reference/extensions/hooks.md), [modules, response-format and attribute handlers](docs/en/reference/extensions/extensions.md), custom auth/casts/hydration, [transport](docs/en/reference/execution/transport.md) and [executor decorators](docs/en/reference/extensions/execution.md). |
| SDK testing | [Fakes, dynamic/file responses, sequences, sent assertions, missing-mock checks and reversible sessions](docs/en/reference/testing/mocking.md); [record/playback](docs/en/reference/testing/fixtures.md) and [live-testing helpers](docs/en/reference/testing/live.md). |
| Scaffolding | [CLI generators](docs/en/reference/client/generation.md) for clients, requests and DTOs in the project's namespace; [runnable examples and the Records SDK](docs/en/examples/README.md). |

## Laravel 13 <a id="section-6"></a>

[apisutra/laravel](https://github.com/apisutra/laravel) connects the same SDK to Laravel:

- **Application integration:** discovery, client/request DI, application defaults, validation, HTTP input mapping and controller responses.
- **Collections:** `Pagination::collect()` wraps the lazy item stream in `LazyCollection`.
- **Testing:** `ApiSutra::for($client)->fake()` with isolated responses, assertions and automatic missing-mock checks.
- **Observation:** execution events, optional Telescope integration and a selected log channel.
- **Queues and tooling:** middleware to release repeatable jobs on SDK throttling, Artisan generators and `artisan about`.

The adapter installs the core too. [Use an SDK in Laravel](https://github.com/apisutra/laravel/blob/master/docs/en/guides/integration/laravel.md) · [Add Laravel support to your SDK](https://github.com/apisutra/laravel/blob/master/docs/en/guides/sdk/laravel.md).

## Choose your next step <a id="section-8"></a>

| Your task | Start here |
| --- | --- |
| Build an SDK for an API | [Create an SDK](docs/en/start/create-sdk.md) |
| Use an existing SDK in an application | [Use an SDK](docs/en/start/use-sdk.md) |
| Try features locally | [Runnable examples](docs/en/examples/README.md) |
| Check settings, behavior or limitations | [Reference](docs/en/reference/README.md) |
| Work with an AI coding agent | [Usage instructions and capability map](docs/en/start/agent.md) |

## Contributing <a id="section-9"></a>

[Development guide](https://github.com/apisutra/php/blob/master/docs/en/development/README.md) · [Agent instructions](https://github.com/apisutra/php/blob/master/.agents/README.md) · [Contributing](https://github.com/apisutra/php/blob/master/CONTRIBUTING.md).

<a id="section-10"></a>
[Changelog](CHANGELOG.md) · [MIT license](LICENSE).
