<!-- languages --> <a href="downloads.md">English</a> · <a href="../../../ru/reference/files/downloads.md">Русский</a> <!-- /languages -->
# Downloading files <a id="section-1"></a>

## Downloading files <a id="section-2"></a>
```php
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Download;

#[Get('/files/{id}')]
#[Download]
final class DownloadFile extends AbstractRequest
{
    public function __construct(public int $id) {}
}

$file = (new DownloadFile(10))->send()->dataOrFail();
$file->saveTo('/tmp/file.pdf');
```

`FileResponse` supports:
- `content()` / `stream()`.
- `filename()` / `mimeType()` / `size()`.
- `isArchive()` / `asArchive()`.
- `saveTo()` / `close()`.

### Streaming downloads without additional configuration <a id="section-3"></a>

`#[Download]` automatically receives the response into a temporary file on disk.
Only chunks of data are held in memory; `FileResponse` becomes available after HTTP
completes. No buffer size or directory configuration is needed. The system temporary
directory must have enough space for the response and any results retained at the same time.

`stream()` returns a readable stream positioned at 0; `size()` returns the actual
number of bytes received, including the valid empty-file size of 0. `content()`
explicitly reads the entire file into a string and requires enough memory.
`asArchive()` also materializes the contents; a streaming download does not make
archive parsing a streaming operation.

### Saving directly to a path or stream <a id="section-4"></a>

```php
$file = (new DownloadFile(10))
    ->withDownloadTo('/tmp/report.pdf')
    ->send()
    ->dataOrFail();
```

The new `withDownloadTo()` protects an existing file: replacement requires
`overwrite: true`. It accepts a local path; the directory must exist.
Stream wrappers, final-component symlinks, and directories used as file targets are
rejected before HTTP. The name from `Content-Disposition` is not used as the destination path.

The SDK first receives the file into a temporary file beside the destination. After
the final accepted 2xx response, hooks, and budget check, it atomically publishes the
result. Failed HTTP, hook rejection, or retry neither overwrites the previous file
nor mixes attempt bodies. Publication without overwrite also preserves a file that
appears during the request. If the filesystem does not support the atomic operation,
an error is returned. This does not guarantee durability against hardware failure.

```php
use GuzzleHttp\Psr7\Utils;

$sink = Utils::streamFor(fopen('/tmp/report-copy.pdf', 'w+b'));
try {
    $file = (new DownloadFile(10))->withDownloadTo($sink)->send()->dataOrFail();
} finally {
    $sink->close();
}
```

A user-supplied `StreamInterface` must be writable. The SDK copies only the final
successful response, starting at the destination's current position; it does not
rewind, truncate the tail, or close it. Write-only and non-seekable destinations are
allowed; `overwrite` does not apply to streams. If final copying fails, the destination
may contain part of the file. The error reports `bytesWritten` and `partial`;
a local write error does not trigger an HTTP retry.

The result remains a `FileResponse` with its own readable stream, even for a write-only
destination. `withoutDownloadTo()` removes the target and restores ordinary download
behavior. These runtime options apply only to requests with `#[Download]` and are
not inherited by child requests.

Existing `FileResponse::saveTo()` retains its overwrite behavior, but also checks
writes and publishes files atomically. It reads a seekable source from the beginning,
and a non-seekable source from its current position. `isArchive()` does not read magic
bytes from a non-seekable stream, to avoid consuming its first bytes; MIME remains available.

### Resource ownership, errors, and diagnostics <a id="section-5"></a>

The SDK does not close user-supplied upload/sink streams. `FileInput` handles created
by SDK factories remain available after sending while their owner is alive; use
`close()` to release them early. Reuse requires managing the position.

`FileResponse` and the raw response share a stream. Releasing one reference does not
close it for the other. Explicit `FileResponse::close()` closes the shared stream;
the last owner also releases the temporary file. The final saved file is not deleted.
A retained raw error response keeps its stream available for diagnostics.

HTTP timeouts cover body reception; the total budget is also checked during copying
and before publication. The SDK cannot forcibly interrupt an individual blocking call
to an arbitrary user stream. Local errors use `file_transfer_error`; budget exhaustion
uses `timeout`. A final logger error after saving does not undo a successful file operation.

File uploads/downloads are automatically excluded from the HTTP cache. Explicitly
enabling it with `withCache()`/`#[Cache]` causes `configuration_error` before HTTP;
`withoutCache()` removes the conflict. This also applies to Base64 uploads with
`FileInput`. Ordinary JSON strings are not treated as files merely because their contents look similar.

Debug and recorder output contains stream metadata; it does not read the file or
change its position. Even `requestDebug(false)` does not create a string copy.
Use a [manual file fixture](../testing/mocking.md#section-4) to replay files.

### Replacing a file body in a hook <a id="section-6"></a>

`withBody($json)` replaces the stream with a string, `withStream($stream)` selects
a new stream, and `withoutBody()` removes the entire outgoing body. When changing
multipart to JSON, explicitly set `Content-Type: application/json`. Old
Content-Length/Transfer-Encoding headers are removed automatically; the original
stream stays open. Clearing an upload does not enable HTTP caching or reset the
download target, destination, or total budget. A stream added by a hook requires
`FileStreamingInterface` even when the original request had no file.
See [body management](../execution/transport.md#section-3) for the full contract and an example.

### Stream and response representation <a id="section-7"></a>

- Binary data is in `PreparedRequest.stream`, with `body = null`; account for this
  in custom hooks/adapters. A nonzero position is preserved.
- Non-seekable binary is not copied to a string: retry is prohibited.
- A download has `ProviderResponse.body = null`; the full response is available in
  `stream`. `json()`/`jsonStrict()` reject such responses instead of implicitly reading
  the file. Ordinary JSON/text responses retain their string contract.
- `errorMessage()` reads a JSON message from a streamed response only if the entire
  body fits within 64 KiB, restoring the position afterwards. For a larger body it uses
  `HTTP <status>`; the full raw stream is retained.
- Explicit HTTP caching of files is unsupported. Third-party transports,
  PSR clients, and retry handlers need the [FileStreamingInterface contract](../execution/transport.md#section-4).
- Base64 JSON, explicit `content()`/`asArchive()`, and ordinary responses without
  `#[Download]` still fully materialize content. No universal JSON size limit is introduced.

## Base64 in responses <a id="section-8"></a>
A working DTO with an incoming data URI and reverse serialization is shown in the
[DTO showcase](../../guides/dto/showcase.md#section-7).

When a DTO field is typed as `Base64File`, the SDK automatically decodes the string:
```php
use ApiSutra\VO\Files\Base64File;

public Base64File $document;
```

Details:
- Built-in `Base64File` expects a **plain base64 string**.
- If the provider returns a data URI such as `data:image/jpeg;base64,...`, use an explicit `DataUriBase64FileCast`.
- For `list<data-uri-string>`, use `Nested(type: Base64File::class, itemCast: DataUriBase64FileCast::class)`.

Example:
```php
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Casts\DataUriBase64FileCast;
use ApiSutra\VO\Files\Base64File;

#[Cast(DataUriBase64FileCast::class)]
public ?Base64File $photo = null;
```

`DataUriBase64FileCast`:
- Accepts plain base64.
- Also accepts data URIs with the `data:...;base64,` prefix.
- Passes an already normalized base64 payload to `Base64File`.
