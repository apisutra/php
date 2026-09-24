<!-- languages --> <a href="showcase.md">English</a> · <a href="../../../ru/guides/client/showcase.md">Русский</a> <!-- /languages -->
# Client capabilities in one example <a id="section-1"></a>

Build a client for a fictional records API and configure it for different tasks:
authentication, resilience, caching, DTOs, and diagnostics. Each section adds one
capability; combine settings according to your API's requirements.

The [complete example](../../examples/client-showcase.md) runs every scenario
without network access or real credentials. In an installed package:

```bash
php vendor/apisutra/php/docs/example/client-showcase/run.php
```

| Task | Where to look |
| --- | --- |
| Set up a connection and get a typed response | [Client and transport](#section-2) |
| Choose credentials for an operation | [Authentication](#section-3) |
| Recover from transient errors and respect quotas | [Retries](#section-4), [rate limits](#section-5) |
| Read again without HTTP | [Caching](#section-6) |
| Check responses and control outgoing DTOs | [Data rules](#section-7) |
| Find the cause of an error | [Diagnostics](#section-8) |
| Set another address or configure one execution | [Copies and one-off options](#section-9) |

## Client and transport <a id="section-2"></a>

[DemoClient](../../../example/client-showcase/src/DemoClient.php) extends `AbstractClient`
and provides a `records()` resource. [GetRecordRequest](../../../example/client-showcase/src/Resources/Records/Get/GetRecordRequest.php)
uses attributes to declare GET `/records/{id}` and `Returns(RecordDto::class, unwrap: 'data')`.
The operation has no retry/cache settings of its own, so client settings apply.

In this example the transport returns a local response. Code after transport setup
is identical for real HTTP; connecting `HttpTransport` is covered in the
[standalone guide](../integration/standalone.md#section-3).

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Example\ClientShowcase\DemoClient;
use Example\ClientShowcase\Resources\Records\Get\GetRecordRequest;
use Example\ClientShowcase\Resources\Records\Save\SaveRecordRequest;

$payload = ['data' => ['id' => 7, 'title' => 'Первая запись', 'future_flag' => true]];
$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake([
    GetRecordRequest::class => MockResponse::success($payload),
    SaveRecordRequest::class => MockResponse::success(['saved' => true]),
]);

// 1. Basic setup: configuration and transport are passed separately.
$base = new ClientConfig(
    baseUrl: 'https://api.example.test',
    timeout: 15,
    connectTimeout: 3,
);
$client = new DemoClient($base, $transport);
$record = $client->records()->get(7)->send()->dataOrFail();
```

`$record` is a typed [RecordDto](../../../example/client-showcase/src/Resources/Records/RecordDto.php)
with `id = 7` and `title = 'Первая запись'`. The client prepares the URL
`https://api.example.test/records/7`, an HTTP request timeout of 15 seconds, and a
connection timeout of 3 seconds. The mock checks that these options are passed;
actual network waiting is not simulated here.

Later sections use `$base`, `$payload`, and `$transport` from this section.
Snippets run after the [example bootstrap](../../../example/sdk/bootstrap.php).

## Authentication <a id="section-3"></a>

Pass a default strategy and, if needed, named strategies.
Every token in this example is fictional; the application supplies its own credentials.

```php
use ApiSutra\Auth\ApiKeyAuthenticator;
use ApiSutra\Auth\BearerAuthenticator;
use Example\ClientShowcase\DemoClient;

$authenticated = $base->with(
    auth: new BearerAuthenticator('demo-user-token'),
    authScopes: ['service' => new ApiKeyAuthenticator('demo-service-key')],
);
$client = new DemoClient($authenticated, $transport);
$client->records()->get(7)->send()->dataOrFail();
$client->send($client->records()->get(7)->withAuthScope('service'))->dataOrFail();
$client->send($client->records()->get(7)->withoutAuth())->dataOrFail();
```

| Call | What is sent |
| --- | --- |
| Ordinary call | `Authorization: Bearer demo-user-token` |
| `withAuthScope('service')` | `X-Api-Key: demo-service-key`; the selected strategy replaces the primary one |
| `withoutAuth()` | A request without these credentials |

For an API with a query key, choose this alternative configuration:

```php
use ApiSutra\Auth\ApiKeyAuthenticator;

$queryAuth = $base->with(auth: new ApiKeyAuthenticator('demo-query-key', header: null, query: 'api_key'));
```

The result is `/records/7?api_key=demo-query-key`. Other options include
[Basic, HMAC, and custom schemes](../../reference/auth/strategies.md),
[refreshable tokens](../../reference/auth/tokens.md), and
[credentials in request fields](../../reference/auth/credentials.md).
Attribute, scope, and auth-policy precedence is in the [auth contract](../../reference/auth/strategies.md).

## Retries for transient errors <a id="section-4"></a>

For our GET, a retry after HTTP 503 is allowed:

```php
use ApiSutra\Config\RetryConfig;

$resilient = $base->with(
    retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false),
);
```

A client with this configuration receives 503, makes a second attempt, and returns success.
`attempts: 2` includes the first attempt. The zero interval keeps the local example fast;
for HTTP, choose suitable `baseDelay`, `maxDelay`, backoff, and jitter.
`totalTimeoutMs` limits the overall budget, including waits and retries.

Without `retry`, configuration-level retries are disabled. Operation attributes or
runtime options may configure them separately; `withoutRetry()` disables retries for
one execution. Declare POST retry safety according to the API contract.
See [retry settings, precedence, and safety](../../reference/execution/retry.md).

## Request rate limits <a id="section-5"></a>

```php
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;

$limited = $base->with(
    rateLimit: new RateLimitConfig(limit: 2, period: 60, behavior: RateLimitBehavior::Throw),
);
```

One client permits two HTTP requests per 60-second window. The third gets
`rate_limited` with reason `local_rate_limit_exceeded` before reaching the transport.
With ordinary `send()`, the error is stored in the result; `dataOrFail()` throws an exception.
`Wait` instead of `Throw` allows waiting for quota availability.

The local quota belongs to the client instance. For multiple workers, connect a
[shared Redis backend](../../reference/integrations/redis.md).
An operation quota supplements the shared quota; retries consume new permits, while
cache hits do not. See the [complete rate-limit contract](../../reference/execution/rate-limit.md).

## Response caching <a id="section-6"></a>

Pass a PSR-16 store. Here an in-memory tutorial store is used; the application
supplies its own store with the required lifetime.

```php
use ApiSutra\Config\CacheConfig;
use Example\ClientShowcase\DemoClient;
use Example\ClientShowcase\Support\MemoryStore;

$store = new MemoryStore();
$cached = $base->with(cacheConfig: new CacheConfig(store: $store, ttl: 60))
    ->with(timeout: 7); // The copy preserves the store and all cache settings.
$cachedClient = new DemoClient($cached, $transport);
$first = $cachedClient->records()->get(7)->send()->dataOrFail();
$second = $cachedClient->records()->get(7)->send()->dataOrFail();
```

Two reads within the TTL perform one HTTP request. The cache stores the API response,
from which a DTO is created every time: `$first !== $second`. This is separate from metadata caching.

| Action | Observation in the example |
| --- | --- |
| Two identical reads | 1 HTTP call in total |
| Another read with `withoutCache()` | 2 HTTP calls in total |
| `clearCache()` on the client, then another read | 3 HTTP calls in total |

ApiSutra separates namespaces by connection and known auth identity;
identical connections may share a cache. This is TTL caching without automatic
HTTP revalidation. Allowed methods, tenants, custom authenticators, and read/write
modes are covered in the [cache reference](../../reference/execution/cache.md).

## DTO input and output <a id="section-7"></a>

[RecordDto](../../../example/client-showcase/src/Resources/Records/RecordDto.php) declares
`int $id`, `string $title`, and `#[Extras] public array $_extra = []`. Enable strict
scalar checking and collection of unknown fields:

```php
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;

$hydration = new HydrationConfig(policy: new RulePolicy(scalars: ScalarPolicy::Strict));
$typed = $base->with(hydration: $hydration);
```

Use the same block standalone: `Hydrator::forConfig($hydration)`. RulePolicy.naming
provides a shared naming policy; it does not change outgoing ClientConfig.namingStrategy.
Add external rules for third-party models as `HydrationConfig(rules: $rules)`;
see [precedence and the model profile](../../reference/dto/configuration.md#section-2).

| Scenario on a client with `$typed` | Result |
| --- | --- |
| Receives `future_flag: true` | `$record->_extra = ['future_flag' => true]` |
| Receives string `id: "7"` | `hydration_error`, path `data.id` |
| `toArray()` on a valid DTO | `id`, `title`, and `_extra` under its own name |
| The same DTO is passed to `SaveRecordRequest` through `BodyRoot` | JSON contains only `id` and `title` |

The `_extra` field is declared explicitly; Extras enables collection. The name alone
does not define behavior. The hydrator and client serializer use the same description
resolver; the receiver is also excluded on manually created objects without prior hydration.

For ordinary request-field formatting, use `queryArrayFormat`, `serializeNulls`,
date and enum policies, and `wireBodySerializationPolicy`:
see [serialization settings](../../reference/serialization/README.md).
Attributes, mapping, nesting, variants, and scoped handlers are shown in the
[DTO capabilities showcase](../dto/showcase.md).

## Diagnostics and errors <a id="section-8"></a>

Connect a PSR-3 logger and enable debug to inspect the prepared request:

```php
use Example\ClientShowcase\DemoClient;
use Example\ClientShowcase\Support\MemoryLogger;
use Psr\Log\LogLevel;

$logger = new MemoryLogger();
$diagnostic = $authenticated->with(localization: 'ru', logger: $logger, logLevel: LogLevel::INFO, debug: true);
$diagnosticClient = new DemoClient($diagnostic, $transport);
$execution = $diagnosticClient->records()->get(7)->withTraceId('record-7');
$handle = $diagnosticClient->send($execution);
$result = $handle->raw();
$trace = $result->trace; // executionId distinguishes calls sharing one traceId.
$debug = $handle->requestDebug();
$debugJson = $handle->requestDebugJson();
```

The result's `traceId` and the log's `trace` equal `record-7`; `executionId` distinguishes
individual executions, and `parentExecutionId` links a child call to its parent.
In `requestDebug()`, the `Authorization` header is replaced with `***`. The safe snapshot
and raw `debug` differ; configure additional secret fields through `redaction`.
See [logs, levels, and diagnostics](../../reference/results/observability.md).

`localization: 'ru'` selects Russian for ApiSutra messages, including logs;
English is the default. See [custom translations and localization boundaries](../../reference/client/localization.md).

For HTTP 404, the example checks three handling approaches:

| Approach | Behavior |
| --- | --- |
| `send()->raw()` | A result with `not_found` for custom branching |
| `send()->dataOrFail()` | An exception instead of the missing DTO |
| Client with `throwOnErrors: true` | An exception directly from `send()` |

See the [complete result and exception contract](../../reference/results/handles.md).

## Configuration copies and one-off options <a id="section-9"></a>

```php
use ApiSutra\Request\RequestOptions;
use Example\ClientShowcase\DemoClient;

$preview = $authenticated->with(baseUrl: 'https://preview.example.test', timeout: 4, auth: null);
$previewClient = new DemoClient($preview, $transport);
$previewClient->records()->get(7)->send()->dataOrFail();
$request = $client->records()->get(7);
$options = RequestOptions::empty()->withTimeout(2, connectTimeout: 1)->withoutAuth();
$client->send($request->withOptions($options))->dataOrFail();
$request->send()->dataOrFail();
```

A `ClientConfig` copy is used to create a new client. Explicit `auth: null` resets
the primary strategy; named `authScopes` remain.
Preview uses a different address, a 4-second timeout, and sends no Bearer token.
The one-off call on the original client gets 2 seconds and runs without auth.
The next call on **the same request** again uses 15 seconds and the original Bearer token.

`with()` does not rebuild an existing client. Runtime methods return an execution copy;
their precedence depends on the setting.
[All ClientConfig parameters](../../reference/client/configuration.md) ·
[Single-request options](../../reference/request/declaration.md#section-14).

## Further capabilities <a id="section-10"></a>

| Need | Continue with |
| --- | --- |
| Create clients through a container or Laravel | [Dependency assembly](../../reference/client/construction.md), [Laravel](https://github.com/apisutra/laravel/blob/master/docs/en/guides/integration/laravel.md) |
| Connect multiple services and versions | [Mega-client](../integration/multi-service.md), [versions](../../reference/client/versioning.md) |
| Configure pages, job waiting, and parallel calls | [Pagination](../recipes/pagination.md), [continuation](../recipes/continuation.md), [batch/pool](../../reference/execution/batch-pool.md) |
| Add custom behavior | [Extensions](../recipes/extensions.md), [error mapping](../../reference/results/errors.md) |

[Executable example](../../examples/client-showcase.md) · [Documentation](../../README.md).
