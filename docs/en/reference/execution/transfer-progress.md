<!-- languages --> <a href="transfer-progress.md">English</a> · <a href="../../../ru/reference/execution/transfer-progress.md">Русский</a> <!-- /languages -->
# HTTP transfer progress <a id="section-1"></a>

Observe uploaded/downloaded bytes with `withTransferProgress(callable)` on a request,
execution chain or `RequestOptions`. It is off by default; `withoutTransferProgress()`
removes it from a copied set of options. The original request/options remain unchanged.
There is no global observer or mandatory client configuration.

## Observe a transfer <a id="section-2"></a>

Given an SDK request already attached to its client:

```php
use ApiSutra\VO\Http\TransferProgress;

$latest = null;
$handle = $request
    ->withTransferProgress(static function (TransferProgress $progress) use (&$latest): void {
        $latest = $progress;
    })
    ->sendAsync()->wait();

$data = $handle->dataOrFail();
// Read/render the latest snapshot outside the callback.
```

The immutable snapshot has these public properties:

| Property | Meaning |
| --- | --- |
| `trace` | The execution's `ExecutionTrace`, including parent and execution IDs |
| `attempt` | Actual HTTP attempt within that execution, starting at 1; includes auth recovery |
| `uploaded`, `downloaded` | Bytes reported by the HTTP library for this attempt |
| `uploadTotal`, `downloadTotal` | Expected transport bytes, or `null` when unknown |

Counters restart on each attempt. A snapshot is not a completion signal: use the result
to determine success. Retry may start after a seemingly complete transfer. Multipart
and chunked framing can contribute bytes; gzip counters can differ from the saved file.
They do not measure confirmed business processing or the original file's size.

For Guzzle/cURL, positive totals are kept and zero becomes `null`, including an empty
body with `Content-Length: 0`. No header analysis distinguishes those cases. Zero byte
counters remain zero. A newly known total triggers a snapshot even if bytes have not
changed. Identical consecutive counters may be suppressed; frequency and final 100%
are not guaranteed.

## Callback and lifetime <a id="section-3"></a>

The callback runs inside the transfer. It must not perform I/O, call the SDK/event loop,
or suspend a Fiber. Store a small snapshot in memory and render/export it elsewhere.
This is an extension contract, not a technical sandbox preventing suspension.

Any exception from the callback disables observation for that execution, including
later retries. It does not fail the request or neighboring concurrent transfers.
Each pagination page is a separate execution: the same callable is active again there
and can fail once per page. Auth, polling and other dependency requests do not inherit
the callback automatically. Trace and attempt are captured before sending; observation
does not depend on the ambient context of a scheduler callback.

Cache hits, early returns and failures before sending emit no progress. Neither do
the built-in MockTransport, playback or Laravel fake. They accept the option, so code
using these fakes needs no environment-specific branch or synthetic progress event.

Cancellation/abandonment and completion close the attempt's receiver; late notifications
are ignored. No progress history or per-snapshot audit/log entry is collected. State is
bounded per execution/attempt; retained request options may still own the user's callable.
Keeping every snapshot in an application array would add its own unbounded history.

## Transport support <a id="section-4"></a>

The built-in Guzzle/cURL adapter supports sync/async uploads and downloads, including
isolated external URLs. It passes progress per send without expanding the isolation
allowlist. SDK observation overrides a native Guzzle `progress` default for that send;
without SDK opt-in the existing default is untouched.

SDK progress with low-level `curl` overrides gives `configuration_error` before HTTP,
including auth dependencies. This uses the same conservative boundary as file streaming
and URL isolation; even a nonconflicting raw cURL option is refused in this combination.

A custom transport and its HTTP adapter must declare `TransferProgressInterface` and
implement `assertSupportsTransferProgress()`. The adapter also needs
`HttpClientOptionsInterface` to receive options. Without capability, opt-in fails before
auth/cache, even on a cache hit; existing adapters work as before when no progress is
requested. A custom test double must declare capability too. RecordingTransport delegates
support and options, and does not serialize callbacks or progress events into cassettes.

For extension authors, `TransportOptions::transferProgress` is a nullable closure receiving
four raw counters in order: download total, downloaded, upload total, uploaded, all in
bytes. Invoke it only during that send. The SDK supplies a protected receiver which owns
trace/attempt and isolates application errors. Preserve it when copying options;
`effective()` and `withTransferProgress()` do so. The transport does not choose identities,
retry policy or execution outcomes. A fake declares capability but emits no counters.

## Runnable example <a id="section-5"></a>

Requires the optional `guzzlehttp/guzzle` package, ext-curl and `proc_open`. It starts
only a local HTTP server and demonstrates binary upload, async download with retry,
and a download without a known total:

```bash
php vendor/apisutra/php/docs/example/transfer-progress/run.php
```

[Source](../../../example/transfer-progress/run.php) · [Retries](retry.md) ·
[Streaming files](../files/uploads.md) · [Async and cancellation](transport.md).
