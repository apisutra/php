<p align="center"><img src="docs/assets/apisutra-logo.png" alt="ApiSutra logo" width="233"></p>
<h1 align="center">ApiSutra</h1>
<h2 align="center">Build PHP SDKs for external APIs</h2>

<p align="center">
  <a href="https://github.com/apisutra/php/actions/workflows/tests.yml"><img src="https://github.com/apisutra/php/actions/workflows/tests.yml/badge.svg?branch=master&amp;event=push" alt="Tests"></a>
  <a href="https://github.com/apisutra/php/actions/workflows/tests.yml?query=branch%3Amaster"><img src="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fapisutra%2Fphp%2Fbadges%2Ftest-count.json" alt="Test count"></a>
  <a href="https://github.com/apisutra/php/actions/workflows/docs.yml"><img src="https://github.com/apisutra/php/actions/workflows/docs.yml/badge.svg?branch=master&amp;event=push" alt="Docs CI"></a>
  <a href="docs/en/README.md"><img src="https://img.shields.io/badge/docs-EN%20%2F%20RU-2563eb" alt="Documentation: EN / RU"></a>
  <a href="composer.json"><img src="https://img.shields.io/badge/PHP-8.4%2B-777BB4" alt="PHP 8.4+"></a>
  <a href="https://packagist.org/packages/apisutra/php"><img src="https://img.shields.io/packagist/v/apisutra/php" alt="Packagist"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-green" alt="MIT license"></a>
</p>

<!-- languages --> <a href="README.md">English</a> · <a href="docs/ru/overview.md">Русский</a> <!-- /languages -->

ApiSutra is a PHP foundation for SDKs that call external APIs. Declare requests and response DTOs once; reuse authentication, retries, pagination, and error handling across your integration.

For SDK authors, it provides a consistent way to describe an API. For applications, it provides typed data, concurrent calls, and diagnostics behind the same client. **PHP 8.4+.** Works standalone; [Laravel 13 integration](#section-6) is a separate package.

[Quickstart](docs/en/guides/quickstart.md) · [Capabilities](#section-5) · [Documentation](docs/en/README.md)

## Install and try <a id="section-7"></a>

```bash
composer require apisutra/php
php vendor/apisutra/php/docs/example/sdk/run.php
```

The included Records SDK runs with mock responses: no API keys or network access needed. When using an existing SDK, follow its installation and credential instructions.

## From a request to a DTO <a id="section-1"></a>

These snippets use the [Records SDK](docs/example/sdk/src/DemoClient.php); imports are omitted. Its API address is illustrative. The runnable example above includes the complete setup.

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

The request describes its route, up to three attempts, and how to read the response. [Request declarations](docs/en/reference/request/declaration.md) also cover query, headers, body, and validation.

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

The application receives objects with dates, enums, nested models, and collections already converted. Use [native DTO rules](docs/en/guides/dto/showcase.md) or a [custom hydrator](docs/en/reference/dto/hydrators.md) for your own factory or mapping library. DTO `toArray()` follows the configured serialization rules; HTTP output has its own settings.

### Send and use the result <a id="section-2"></a>

```php
$client = new DemoClient(
    new ClientConfig(baseUrl: 'https://api.example.test'),
    HttpTransport::createDefault(),
);

$handle = $client->send($client->records()->get(7));
/** @var GetRecordResponseDto $record */
$record = $handle->dataOrFail();
echo $record->createdAt->format('Y-m-d');
```

`send()` waits for completion and returns a `ResultHandle`. `dataOrFail()` reads its data and throws on FAILED; `resolved()` exposes status and errors, while `raw()` provides the execution result, HTTP response, and diagnostics. Reading the same handle does not send again. [Results and errors →](docs/en/reference/results/handles.md)

## Independent calls and large datasets <a id="async-and-pagination"></a>

Start independent requests before waiting to overlap HTTP with the built-in Guzzle transport:

```php
$first = $client->sendAsync($client->records()->get(7));
$second = $client->sendAsync($client->records()->get(8));

$record7 = $first->wait()->dataOrFail();
$record8 = $second->wait()->dataOrFail();
```

`sendAsync()` returns a [typed, Guzzle-compatible promise](docs/en/reference/results/promises.md). No worker or manual event-loop setup is needed. Keep and await the promises; this is not fire-and-forget.

For a paginated SDK request already bound to its client, process elements as pages arrive. Here `$repository` belongs to the application:

```php
foreach ($request->paginate()->items() as $item) {
    $repository->save($item);
}
```

The [item stream](docs/en/reference/execution/pagination-items.md) loads pages sequentially without retaining the entire dataset. A FAILED page throws instead of silently ending the list. Use `all()`, `pages()`, or `range()` to collect pages, with [concurrency for independent pages](docs/en/reference/execution/pagination.md#section-21). For many independent requests, [pool `consume()`](docs/en/reference/execution/pool-consumption.md) delivers results to handlers without collecting them all.

## Capability map <a id="section-5"></a>

### Describe your API

| Need | What ApiSutra provides |
| --- | --- |
| <a id="capabilities-sdk"></a> SDK structure | [Clients, resources](docs/en/reference/client/resources.md), service versions and discovery; [operation inventories](docs/en/reference/client/operation-inventory.md) and DTO catalogs for tooling. |
| <a id="capabilities-requests"></a> Requests and input | [Attributes](docs/en/reference/request/declaration.md) for paths, query, headers and bodies; [validation](docs/en/reference/client/validation.md) before HTTP. |
| <a id="capabilities-dto"></a> Response models | [DTOs](docs/en/guides/dto/showcase.md), plain PHP classes, nested collections, enums, dates, strict rules, casts and unknown fields; [custom hydrators with DI](docs/en/reference/dto/hydrators.md). |
| Outgoing data | Separate [DTO serialization](docs/en/reference/serialization/dto-output.md) and [HTTP representation](docs/en/reference/serialization/request-parts.md); JSON, forms, multipart and binary bodies. |
| <a id="capabilities-client"></a> Authentication | API key, Bearer, Basic and [HMAC](docs/en/reference/auth/strategies.md); [OAuth2](docs/en/reference/auth/oauth2.md) Client Credentials, Authorization Code with PKCE, and token refresh. |
| Client settings | [Per-client and per-call configuration](docs/en/reference/client/configuration.md), credential isolation, container integration, and [EN/RU messages](docs/en/reference/client/localization.md). |

### Execute, inspect, and extend

| Need | What ApiSutra provides |
| --- | --- |
| <a id="capabilities-execution"></a> Predictable execution | [Retry and idempotency](docs/en/reference/execution/retry.md), [shared deadlines](docs/en/reference/execution/deadlines.md), cancellation, local quotas and server cooldown; optional [Redis coordination](docs/en/reference/integrations/redis.md). |
| <a id="capabilities-multiple-calls"></a> Concurrent and bulk work | [Typed async](docs/en/reference/results/promises.md), batch/pool, [streaming consumption](docs/en/reference/execution/pool-consumption.md), and [dependent operations](docs/en/reference/request/composition.md). |
| Pagination | [Page, offset and cursor schemes](docs/en/reference/execution/pagination.md), lazy pages/items, concurrent collection of independent pages; concurrent `all()` requires `total` and `perPage`. |
| Deferred API results | [Readiness and polling](docs/en/reference/execution/continuation-await.md) for operations that finish later. |
| Fewer repeated requests | [PSR-16 response caching](docs/en/reference/execution/cache.md), TTL, per-call modes, and credential-aware cache keys. |
| Files | [Streaming uploads](docs/en/reference/files/uploads.md), [downloads to files or streams](docs/en/reference/files/downloads.md), and archive handling. |
| <a id="capabilities-results"></a> Results and diagnostics | [Statuses and errors](docs/en/reference/results/errors.md), custom result methods, [sync/async trace, audit, debug and redaction](docs/en/reference/results/observability.md), plus [execution observers](docs/en/reference/results/observation.md). |
| <a id="capabilities-extensions"></a> Custom behavior | [Hooks, response handlers and modules](docs/en/reference/extensions/extensions.md), custom transport, auth, casts and hydration. |
| Testing | [Fakes, response sequences and assertions](docs/en/reference/testing/mocking.md); [record/playback fixtures](docs/en/reference/testing/fixtures.md) and live checks. |
| Scaffolding | [CLI generators](docs/en/reference/client/generation.md) for clients, requests and DTOs, using your project's namespace. |

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
