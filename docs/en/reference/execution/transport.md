<!-- languages --> <a href="transport.md">English</a> · <a href="../../../ru/reference/execution/transport.md">Русский</a> <!-- /languages -->
# Transport, promises, and I/O limitations <a id="section-1"></a>

## Promise API and actual execution <a id="section-2"></a>

`send()` remains synchronous and returns a completed ResultHandle. `sendAsync()`
starts execution and returns `ResultPromiseInterface<ResultHandle>`;
`wait()` yields the completed handle. See [types and chains](../results/promises.md).
Start several calls before waiting:

```php
use GuzzleHttp\Promise\Utils;

$first = $client->sendAsync($firstRequest);
$second = $client->sendAsync($secondRequest);
$results = Utils::all([$first, $second])->wait();
```

The built-in Guzzle adapter overlaps HTTP transfers. SDK delay, backoff, Retry-After,
rate-limit and auth-lock waits cooperate with other executions. The same pipeline
handles sync and async: retry safety, authentication, shared deadlines, redaction,
file streaming and result delivery retain their contracts. A cache hit or mock may
finish immediately. Awaiting one promise also progresses other started calls.
Repeated `raw()` / `resolved()` calls do not resend; two waiting Fibers can share one
SDK promise. `then()`, `otherwise()`, `Utils::all()` and `Each` are supported.

No loop configuration is required. Core depends on `revolt/event-loop`; an internal
scheduler adapter owns only SDK timers and suspensions, never the application's
loop or error handler. Built-in HTTP needs Guzzle and cURL with AsynchDNS. HTTP uses
nonblocking cURL polling only while transfers are active; idle and timer-only work
have no HTTP poller. Calling `send()` normally creates no scheduler tasks.

Third-party transports implement `ConcurrentTransportInterface::assertSupportsConcurrency()`;
HTTP adapters implement `AsyncHttpClientInterface::assertSupportsConcurrency()` and
`sendAsyncWithOptions(RequestInterface, TransportOptions): PromiseInterface`.
RetryDelayPolicyInterface only computes a delay; it needs no async capability.
These contracts promise progress in the SDK's event loop and cooperative waits,
not just a Promise return type. Unsupported async produces `configuration_error`
before auth, quota or HTTP; use `send()` or a concurrent adapter. There is no implicit
synchronous fallback. MockTransport works without Guzzle HTTP or cURL.

Keep the promise and await its result before a job/request exits. This is not
fire-and-forget. `$promise->cancel()` requests best-effort cancellation and rejects
the promise; it stops subsequent attempts and releases SDK waits, transfers and owned
sinks. Internal diagnostics use `execution_error`, reason `execution_cancelled`.
Already transmitted data cannot be recalled and acquired quota is not refunded.
Dropping all handles abandons pending SDK work; destructors never run HTTP or a loop.
Cancellation of an aggregate also cancels its pending children.

Unchanged request instances may have concurrent `with*` executions: options, context,
trace and pagination helpers belong to each execution. Inside a hook `getContext()`
and protected `$context` refer to its execution; outside execution they expose the
last completed context. Do not mutate the request/client, authenticator or shared
stream while it is in use. Supply independent streams for concurrent uploads.
Synchronous application hooks, stores, phpredis, logging and file access can still
block the process. Native cURL callbacks must not suspend or run nested SDK work.
Platform-specific React/Swoole/Octane/FrankenPHP integration is not supplied.

Downloads are fully received in an owned sink before the promise resolves; the
consumer can read the file later without the event loop. Dependent pagination pages
remain sequential, while unrelated calls can run during each page's HTTP/waits.

Batch/pool manage tasks according to [their contract](batch-pool.md). A deferred
provider operation has a separate [readiness contract](continuation-state.md).

Waiting for a source does not finish all its [waiters](../extensions/execution.md#section-5).

Standard serialization accepts relative endpoints and preserves the base path,
original query, and repeated parameters under the [URI contract](../serialization/uri-query.md#section-5).
Complete and signed URLs are supported through `withUrl()` or an absolute endpoint;
these and external `withBaseUrl()` overrides follow [destination isolation](../serialization/uri-query.md).

## Replacing and clearing the PreparedRequest body <a id="section-3"></a>

Ordinary serialization works automatically without new client settings. This API
is for hooks and custom adapters that change an already prepared HTTP body.
`PreparedRequest` has exactly one source: a `body` string (including `''`), a `stream`,
or no body (both `null`).

| Operation | Result |
| --- | --- |
| `withBody($text)` or `with(body: $text)` | The string replaces the entire body; the previous stream is removed. |
| `withStream($stream)` or `with(stream: $stream)` | The stream replaces the entire body; the previous string is removed. |
| `withoutBody()` | Removes both string and stream. Repeated clearing is allowed. |
| `with(body: null)` / `with(stream: null)` | Preserves the previous body; does not clear it. |

Every method returns a new copy without reading, rewinding, or closing the stream.
Simultaneously non-null `body` and `stream` in the constructor or `with()` cause
`ConfigurationException`, including an empty string. Normal pipeline error handling
remains: `configuration_error`, or an exception with `throwOnErrors`. An invalid
request is not sent; a post-auth hook does not undo authentication already performed.

On replacement/clearing, the SDK removes inherited `Content-Length` and
`Transfer-Encoding` case-insensitively. Explicit `headers` in the same `with()` replaces
the entire header array and is retained as a new snapshot. Before HTTP, the SDK checks
explicit length against the known size of the outgoing body. Invalid or duplicate
`Content-Length`, or its combination with `Transfer-Encoding`, causes
`configuration_error`. Unknown stream size is not determined by reading: the caller
is responsible for an explicitly declared length.

`Content-Type` and application headers are preserved. Set the type explicitly when
changing formats; the SDK does not infer MIME from content. Code that creates custom
digests/signatures must recompute them. Example: replacing multipart with JSON:

```php
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\VO\Pipeline\PipelineContext;

final class ReplacePayloadHook implements HookInterface
{
    public function handle(PipelineContext $context): ?array
    {
        $context->preparedRequest = $context->preparedRequest
            ->withBody('{"mode":"metadata"}')
            ->withHeader('Content-Type', 'application/json');

        return null;
    }
}
```

To clear the entire body in a hook, use
`$context->preparedRequest = $context->preparedRequest->withoutBody()`.
URL, destination policy, auth headers, transport options, total budget, and download
target are preserved. File-operation provenance and its cache prohibition remain too.

Replacement removes the previous `meta.body`/`bodyIsRoot` snapshot. Debug shows the
new string in `bodyRaw` or `hasStream: true`, without reading the stream or rebuilding
structure from raw body data. With `debug: true`, final debug uses the request from
the received response, or the current context for an error without a response.
Redaction applies normally. Changing the body between attempts prohibits retry with
`body_changed`; see [retry details](retry.md).

`with(body: ...)` replaces a stream with a string. Choose one source instead of
simultaneous `body`/`stream`. Use `withoutBody()` to clear the body; `with(...: null)`
retains it. Update `Content-Type` when changing formats; stale framing headers
are removed automatically.

## Streaming files <a id="section-4"></a>

The built-in transport automatically supports binary/multipart uploads and
`#[Download]` with bounded memory use. Third-party transports, PSR clients, and
custom retry handlers must implement `FileStreamingInterface::assertSupportsFileTransfer()`.
The check runs before auth/HTTP. PSR-18 alone does not guarantee bounded memory;
an unsupported adapter causes `configuration_error`, without a string fallback.

`FileTransferOptions` describes upload/download and an optional target. The mode is
passed through `PreparedRequest.fileTransfer` and `TransportOptions.fileTransfer`.
For downloads, `HttpTransport` creates a separate sink for each attempt in
`TransportOptions.sink`. The PSR client must write directly to it and return it as
the PSR response body without closing the sink. `HttpTransport` returns
`ProviderResponse(body: null, stream: ...)`. For ordinary responses, `body` remains
a string and `stream = null`.

Adapters must preserve the upload range and not close borrowed streams. They must
not materialize a large file, enable a hidden sink/debug, alter the body through
defaults, or add redirects. Built-in Guzzle suppresses those file defaults and rejects
raw cURL overrides for streamed calls. Timeouts and origin policy still apply.
Custom retry handlers must pass file transport options unchanged.

See the [file guide](../../guides/recipes/files.md) for ownership.
The package gains no new required dependencies; a standalone adapter can operate
without Laravel and Guzzle HTTP Client.

## Contract <a id="section-5"></a>
```php
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use GuzzleHttp\Promise\PromiseInterface;

interface TransportInterface
{
    public function send(PreparedRequest $request): ProviderResponse;
    public function sendAsync(PreparedRequest $request): PromiseInterface;
}
```

## HttpTransport (PSR-18/PSR-17) <a id="section-6"></a>
The default implementation uses:
- PSR-18 `ClientInterface`.
- PSR-17 `RequestFactoryInterface` and `StreamFactoryInterface`.

In Laravel, `SdkServiceProvider` automatically creates `GuzzleHttpClient` with a
known cURL handler unless a custom PSR client/transport is supplied. Outside Laravel:

```php
use ApiSutra\Transport\HttpTransport;

$transport = HttpTransport::createDefault();
// Pass $transport to the SDK client constructor.
```

This built-in setup requires `guzzlehttp/guzzle` and `ext-curl`. Guzzle HTTP Client
remains an optional core dependency. PSR-17 factories are already package dependencies.
The 30/10-second defaults apply automatically.

### Custom HTTP clients <a id="section-7"></a>

The `HttpTransport($httpClient, $requestFactory, $streamFactory)` constructor remains.
To apply SDK limits, a PSR-18 client also implements `HttpClientOptionsInterface`:
`assertSupportsTimeouts(TransportOptions)` checks capabilities, and `sendWithOptions()`
applies `effective()` immediately before HTTP. Built-in `GuzzleHttpClient` implements
both contracts. For extra parameters:

```php
use ApiSutra\Transport\GuzzleHttpClient;
use GuzzleHttp\Psr7\HttpFactory;

$factory = new HttpFactory();
$transport = new HttpTransport(
    new GuzzleHttpClient(['verify' => '/path/to/ca.pem']),
    $factory,
    $factory,
);
```

The adapter supplies `timeout` and `connect_timeout` separately for each send without
changing the global HTTP client. `http_errors=false` and `allow_redirects=false`
keep 4xx/5xx and redirect handling in the SDK. `sendAsync()` uses the concurrent adapter described above.

An ordinary `GuzzleHttp\Client` passed directly as PSR-18 does not declare support
for these options. For the standard setup, replace it with `GuzzleHttpClient` or use
`createDefault()`. The built-in adapter owns its cURL handler and rejects
`config['handler']`. To use an existing Guzzle client with custom middleware/handler,
retain it in your own `HttpClientOptionsInterface` adapter and explicitly implement
both timeouts. The SDK neither replaces a user stack nor infers capabilities solely
from a class name.

A custom `TransportInterface` supporting limits implements `TimeoutAwareTransportInterface`.
For an unsupported nonzero limit, including defaults, `assertSupportsTimeouts()` must
throw `ConfigurationException` naming the unsupported parameter before HTTP.
Options come from `PreparedRequest::transportOptions`; `effective()` checks the
deadline and caps timeouts by the remaining budget after all waits. All request
copies must preserve options. Direct `send()` with a PreparedRequest lacking options
retains old behavior: that call has no SDK configuration.

For an ordinary PSR-18 client without an adapter, explicitly selecting `timeout: 0`,
`connectTimeout: 0`, and no total budget is allowed. Its own limits remain its
responsibility. An unknown transport with active SDK limits produces
`configuration_error` before sending. Contract, priorities, and restrictions:
[timeouts and delay](deadlines.md).

## TransportResolver for a high-level make API <a id="section-8"></a>
For provider SDKs, such as `ProviderClient::make(...)`, use core `TransportResolver`:
- An explicitly supplied `transport` is used.
- Otherwise it resolves through `ContainerProviderInterface`.
- Failure throws a standardized `ConfigurationException`.

This keeps the client constructor low-level and predictable
(`new Client($config, $transport)`), while `make(...)` provides convenience.

```php
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Support\TransportResolver;

public static function make(
    string $accessKey,
    string $secretKey,
    ?TransportInterface $transport = null,
): self {
    $config = ProviderClientConfigFactory::make($accessKey, $secretKey);
    $resolvedTransport = TransportResolver::resolve($transport, $config->containerProvider);

    return new self($config, $resolvedTransport);
}
```

## MockTransport and RecordingTransport <a id="section-9"></a>
Testing options:
- `MockTransport`: fake responses, patterns, and sequences; accepts options for
  pipeline checks, without actual HTTP or forced interruption.
- `RecordingTransport`: records JSON fixtures.

## PreparedRequest and ProviderResponse <a id="section-10"></a>
`PreparedRequest` contains method, URL, headers, body/stream, meta, and optional `transportOptions`.
`ProviderResponse` contains status, headers, body, and duration.

## Destination isolation <a id="section-11"></a>

For a complete URL or an origin change, the transport must implement
`ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface`:
`assertSupportsDestination(RequestDestination $destination): void` confirms that
actual sending preserves the target and excludes redirects and automatic credentials
from the original client. If impossible, it throws `ConfigurationException` before
I/O. Timeout support alone does not provide this guarantee.

`HttpTransport` also checks its nested PSR client's support. If the client implements
`HttpClientOptionsInterface`, `TransportOptions.destination` passes the selected
destination to `sendWithOptions()` and survives `effective()`. A PSR client without
the options interface may declare this capability only if it ensures it on every
`sendRequest()`. `PreparedRequest.with()` copies preserve `destination`. Before
sending a modified copy, call `DestinationGuard::checkRequest($request)`; a custom
retry handler needs the same capability and check directly at the I/O boundary.

Built-in HttpTransport, MockTransport, and RecordingTransport support the contract;
the recorder delegates the check to its nested transport. `HttpTransport::createDefault()`
selects everything automatically. Ordinary relative same-origin requests do not
require this interface. A direct low-level PreparedRequest without destination
has no original origin: an absolute URL alone does not provide the pipeline guarantee.

The built-in Guzzle adapter uses a separate client for protected calls. It preserves
verify, proxy, force_ip_resolve, version, and timeout settings. Auth, cookies,
arbitrary headers/query/body defaults, callbacks/debug, and client TLS cert/ssl_key
are not inherited. Nonempty low-level `curl` overrides cause an error for protected
calls; compatibility cannot be assumed. Ordinary sending retains the original
configuration. `ExactTargetCurlFactory` preserves the path and empty query delimiter
at the cURL boundary, including absolute-form through an HTTP proxy. Redirects are
not followed automatically.

Hooks and custom PHP remain trusted extensions. The check protects the standard
sending path; it does not isolate code that copies secrets, removes the destination
contract, or accesses the network itself. Destination checking is not an SSRF filter,
DNS policy, or address allowlist.

User API: [external URLs](../serialization/uri-query.md).
