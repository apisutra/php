<!-- languages --> <a href="observability.md">English</a> · <a href="../../../ru/reference/results/observability.md">Русский</a> <!-- /languages -->
# Logs, traces, and debug <a id="section-1"></a>

Diagnostics are available through `ResultHandle::raw()`, the full `ExecutionResult`.
Choose settings according to the task:

| Task | Use | Requirement |
| --- | --- | --- |
| Find a call result and related log records | Result `traceId`, `trace` in logger context | Trace is created without debug/logger too |
| Inspect execution events | `ExecutionResult::audit` | Collected without debug/logger too |
| Inspect the prepared HTTP request | `requestDebug()` / `requestDebugJson()` | `debug: true`; the request must have been prepared |
| Write to application logs | PSR-3 `logger` and `logLevel` | Logger is supplied explicitly; debug is optional |

[Message language](../client/localization.md) is independent: `localization: 'ru'`
enables Russian; English is the default. Technical codes and third-party text are
preserved. The setting also applies to SDK-owned logger messages.

## Quick example <a id="section-2"></a>

For an existing SDK client with `debug: true`, as in the main README:

```php
$execution = $client->records()->get(7)->withTraceId('record-7');
$handle = $client->send($execution);
$result = $handle->raw();

echo $result->traceId;             // record-7: search for this value in logs.
echo $handle->requestDebugJson();  // Request JSON with secrets masked.
$durationMs = $result->debug?->duration; // Execution duration or null.
```

Reading a result does not resend the request. `requestDebug()` returns the same
snapshot as an array; both methods are available on `ResultHandle` and `ExecutionResult`.
The [executable client overview](../../examples/client-showcase.md) demonstrates
trace, audit, logging, and Bearer-token masking with a local response and no network.

## Logger and logLevel <a id="section-3"></a>

`logger` is a PSR-3 logger prepared by the application. Without one, the SDK writes
no logs. `logLevel` is the minimum level, `INFO` by default. Example configuration
with an existing `$logger`:

```php
use ApiSutra\Config\ClientConfig;
use Psr\Log\LogLevel;

$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    localization: 'ru',
    logger: $logger,
    logLevel: LogLevel::DEBUG,
    debug: true,
);
```

| Level | Examples of built-in records |
| --- | --- |
| `INFO` | Request start and successful completion |
| `DEBUG` | HTTP request preparation, response reception, and cache indicator |
| `WARNING` | Retry and refusal of unsafe retry |
| `ERROR` | Validation error, HTTP error, or exception |

`debug: true` collects result snapshots; `logLevel: DEBUG` allows records at that
level. These settings are independent: debug neither attaches a logger nor changes
the logging threshold. Built-in structured log context passes through
[RedactionPolicy](#section-8). A formatting, redaction, or external logger failure
does not change HTTP, data, the original exception, or a saved file. Audit is collected
independently of the sink; logs are not resent through a failed logger.
Business-hook and DTO errors retain normal delivery.

## TraceId <a id="section-4"></a>

`ExecutionResult::traceId` links the result to the `trace` field in PSR-3 log context.
You can pass the identifier of an incoming application request or job:

```php
$execution = $client->records()->get(7)->withTraceId('job-42');
$handle = $client->send($execution);
$traceId = $handle->raw()->traceId; // job-42
```

Without an override, the client creates a random identifier. `withTraceId()` sets it
for one execution; `$client->setTraceId('job-42')` sets the default for subsequent
calls on that client instance. `$client->clearTraceId()` removes the default;
existing results do not change.

Concurrent calls have separate execution IDs and audit logs, even when they reuse
the same unchanged request. Changing the client trace default affects subsequent
starts, not suspended executions. Multiple waiters of one handle observe the same
execution; awaiting it does not create another `started` or terminal event.

Source priority:

1. Runtime `RequestExecution` / `RequestOptions` options (`withTraceId()`).
2. The request's own override (`getTraceIdOverride()`), if provided.
3. Trace passed to the executor: the client value for an ordinary call, parent context for a nested call.
4. Pipeline default, then an automatically generated identifier.

Trace does not automatically add an HTTP header. If the API expects the identifier
in `X-Trace-Id` or another header, set it separately through
[request options](../request/declaration.md#section-14).

## Identity and operation trees <a id="section-5"></a>

`ExecutionResult::trace` is an immutable `ExecutionTrace` with three fields:

| Field | Purpose |
| --- | --- |
| `traceId` | A group of related operations; matches the existing `ExecutionResult::traceId` |
| `executionId` | Unique ID for one execution, including another send of the same request |
| `parentExecutionId` | Parent execution ID, or `null` at the root |

Their log fields are `trace`, `executionId`, and `parentExecutionId`; the machine
`event` is independent of message language. Root trace priority is runtime override →
request override → client default → random ID. A child inherits its parent's trace
unless it has an explicit override. A different explicit trace still retains the
`parentExecutionId` link. IDs are not added to cache keys or HTTP headers.

Composite and DependsOn have their own executions, with children in `nested`.
For DependsOn, the main HTTP request continues the same execution after dependencies
are processed. Pagination has a root and separate page executions; aggregate trace
matches the root. Await creates a waiting node linked to the start result; polls
are its children. Passing trace neither extends nor inherits the start's expired HTTP budget.

Independent items in a standalone batch/pool may have different traces; an aggregate
without a shared execution retains `trace: null`. Child results retain trace, audit,
and debug when exceptions/rejections are converted into results. Localization,
metadata attachment, and reading a completed handle do not create new IDs.
An old manually constructed result or event may have `trace: null`.

## Audit log <a id="section-6"></a>

`ExecutionResult::audit` is an array of `PipelineEvent`. A started execution has one
`started` and one terminal event: `completed` for SUCCESS/PARTIAL or `failed` for FAILED.
Throw/rejection adds no second terminal event. Existing error delivery policy remains:
an aggregated FAILED Composite/pagination result does not itself enable throwing.
A pre-send check failure creates no fictitious HTTP attempt.

```php
foreach ($result->audit as $event) {
    echo $event->stage->value . PHP_EOL;
    $executionId = $event->trace?->executionId; // Link the event to its execution independently of the result.
    $elapsedMs = $event->duration;              // Since execution start; null for started.
    $attempt = $event->context['attempt'] ?? null;
}
```

An ordinary GET: `started → http_request → http_response → completed`. Retry repeats
HTTP-event pairs; `context.attempt` numbers actual attempts. `http_response` contains
`httpStatus` or the send exception class. Auth refresh is a separate child execution.
Cache hits create no HTTP events. With a custom retry handler, one attempt means one
handler invocation: the core cannot see internal sends. Other PipelineStage values
do not by themselves imply a mandatory record at every internal step.

An event contains `stage`, `timestamp`, `duration`, `requestClass`, `role`, `payload`,
`trace`, and a small structured `context`, including machine `event`.
Timestamp is Unix wall-clock time in seconds; duration is monotonic milliseconds
since execution start. Wall-clock changes do not affect duration.
Payload appears only with debug and may remain `null`.

A pagination iterator starts execution when consumed. Natural exhaustion completes
it; releasing an unfinished generator produces `abandoned` with reason
`iteration_stopped`, without another HTTP call. A `break` while retaining the generator
does not release it yet. Abrupt process termination does not guarantee a terminal event.
A repeated traversal creates a new execution; parent logs do not copy child audits.

Explicit async cancellation rejects the public Promise and immediately records
`failed` with `reason: execution_cancelled`. Later Fiber cleanup adds no second
terminal event. Releasing all handles without prior explicit cancellation records
`abandoned` with `reason: execution_cancelled` for each active scope, including nested
requests and continuation waits. Completed scopes stay completed. This closes local
diagnostics without advancing the event loop or issuing HTTP; it does not prove that
the remote server cancelled work already received. Abrupt process termination can
still prevent final records.

## Debug <a id="section-7"></a>

`ClientConfig(debug: true)` enables `ExecutionResult::debug` (`DebugInfo`): the
prepared `preparedRequest`, received `response`, and `duration` in milliseconds.
Individual fields may be `null` if execution did not reach that step.
The response is also available as `ExecutionResult::response` without debug;
its `duration` measures the HTTP exchange rather than the whole SDK execution path.
Read nested results through `nested` and their own `audit`/`debug`; the pipeline
does not construct a shared tree in `DebugInfo::nested`. Collecting pagination has
no root HTTP snapshot; page diagnostics are in `result->nested` in page order, even
when completion order differs. Root duration is in its terminal audit event.

`requestDebug()` exports the **request**, not the response:

| Snapshot fields | Contents |
| --- | --- |
| `method`, `url`, `headers` | Prepared HTTP method, address, and headers |
| `bodyRaw`, `body`, `query`, `form` | Body and structured parts, when available |
| `hasStream` | Whether a stream exists; diagnostics do not read it |
| `bodySize`, `bodyOmitted`, `bodyOmissionReason` | String body size and omission reason |
| `oneOf` | For contract diagnostics: `contract`, `matchedVariant`, `discriminator` |
| `credentialsEnrichment` | For enrichment: `applied`, `scope`, `mergeMode`, affected `fields` |

Without debug or a prepared request, `requestDebug()` and `requestDebugJson()` return
`null`; the JSON method may also return `null` if encoding fails. Use safe snapshots
for error analysis: direct reads of raw `debug`, `response`, and event payloads are not redacted.

## Redaction for safe export <a id="section-8"></a>

**`ClientConfig::redaction` is optional:** `new RedactionPolicy()` is already used
by default. You do not need to create and pass this object to enable baseline
protection. Ordinary `new ClientConfig(baseUrl: ...)` is sufficient.

The shared `RedactionPolicy` applies to `requestDebug()`/`requestDebugJson()`,
built-in PSR-3 logger structured context, and recorded fixtures. It masks standard
credential headers, Cookie/Set-Cookie, known password/token/secret fields, URL userinfo,
and query credentials, including repeated and percent-encoded names.
`credentialsConfig.secretKeys` adds rules for the prepared request. Original HTTP
data and stream position are unchanged.

For fields specific to one operation, implement
`ApiSutra\Contracts\Interfaces\Diagnostics\SensitiveFieldsProviderInterface` on the
request and add this method:

```php
public function sensitiveFields(): array
{
    return ['approval_code', 'verification_secret'];
}
```

Return field names, not secret values. These additive rules apply only to this
request/response's safe exports and recordings; other request types and HTTP data
are unchanged. Built-in OAuth2 requests already declare their extra secret fields.

Pass a policy explicitly when the provider uses **additional** secret headers,
fields, or nested paths absent from built-in rules. If standard rules and
`credentialsConfig.secretKeys` suffice, omit it. For provider-specific credentials:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Diagnostics\RedactionPolicy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    redaction: new RedactionPolicy(
        headers: ['X-Provider-Credential'],
        fields: ['provider_secret'],
        paths: ['accounts.*.credential'],
    ),
);
```

`fields` applies at any depth; `paths` specifies paths inside JSON bodies/data, with
`*` matching one level. Rules add to built-ins without disabling them.
`ClientConfig::with()` preserves the policy; `$client->record()` passes it to the
recorder. Independently constructed `RecordingTransport` also applies baseline
policy automatically; its `redaction` argument is needed only for custom rules.

Invalid JSON in safe export is replaced by `[redacted-body]`; form-urlencoded data
is masked by field names. Arbitrary error text, unstructured text, and nonstandard
formats are not guaranteed to be sanitized: rules do not locate every possible
secret anywhere in a string.

Direct `ExecutionResult::debug`, `response`, and audit payloads remain raw objects.
Arbitrary serialization of them is not a safe export. Deliberate raw request
snapshots are available through `requestDebug(false)` and `requestDebugJson(false)`.

The recorder applies baseline protection even without a custom Fixture. A Fixture
adds rules and replacement values; previously recorded files are not rewritten automatically.

## Safe debug/log size <a id="section-9"></a>

By default, bodies in safe debug/log output are limited to 64 KiB (65536 bytes).
Larger bodies are omitted entirely: `bodyOmitted=true`,
`bodyOmissionReason=body_size_limit`, and `bodySize` holds the original size.
JSON is not truncated. Original HTTP responses and replay fixtures retain their size;
streams are not read or rewound for diagnostics. `ProviderResponse::duration`,
`DebugInfo::duration`, and `PipelineEvent::duration` are in milliseconds;
timestamp remains Unix time in seconds.

RedactionPolicy remains optional. Supply it for additional secret fields or to
increase the limit while retaining masking:

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Diagnostics\RedactionPolicy;

$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    redaction: new RedactionPolicy(maxBodyBytes: 262144),
);
```

When reading large bodies through `requestDebug()`, account for the omission marker
or increase the limit. Explicit `requestDebug(false)` remains raw access without
masking or a size limit; increase the safe limit for ordinary analysis.

## Environment <a id="section-10"></a>

`environment` controls metadata caching independently of debug:

- `Local` / `Testing`: caching disabled.
- `Production` / `Staging`: caching enabled.

The cache stores declaration descriptions rather than shared objects from constructor
defaults or attribute arguments. Environment selection does not change their isolation
between DTOs and operations of one client. See [DTO defaults](../dto/lifecycle.md#section-3)
and [object attribute arguments](../dto/lifecycle.md#section-2).
Environment also participates in auto-discovery with `DiscoveryCacheMode::Auto`.

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;

$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    environment: Environment::Testing,
    debug: true,
);
```
