<!-- languages --> <a href="uploads.md">English</a> · <a href="../../../ru/reference/files/uploads.md">Русский</a> <!-- /languages -->
# Uploading files <a id="section-1"></a>

Formats and sources for outgoing file bodies. See the [practical example](../../guides/recipes/files.md)
for complete client construction and execution.

## Uploading files <a id="section-2"></a>
```php
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\VO\Files\FileInput;

#[Post('/files')]
final class UploadFile extends AbstractRequest
{
    public function __construct(
        #[File('file', format: FileFormat::Multipart)]
        public FileInput $file,
    ) {}
}

$request = new UploadFile(FileInput::fromPath('/tmp/report.pdf'));
$request->send();
```

Supported formats: `Multipart`, `Binary`, `Base64`.

In multipart filenames, `"`, CR and LF are encoded as `%22`, `%0D` and `%0A` before
writing the part header, preventing filename-based header and field injection.
This does not change `FileInput::filename`, file bytes, Unicode characters or literal
percent signs.

### Resending <a id="section-3"></a>

When retry is allowed, the core automatically restores a seekable stream to its
original position, which may be nonzero. For multipart, it preserves file-part
positions, the boundary, and body bytes. No rewind settings are required; the SDK
does not close user-supplied streams. Empty files remain valid.

A non-seekable stream permits the first send, but no retry. If tell/seek is impossible
or the body changes after sending, the SDK stops retrying and preserves the original
error with `retryRefusalReason` in its context. There is no implicit buffering or
use of repeatable stream factories. Replayability is guaranteed only across attempts
of one execution; do not change the file externally or use one stream concurrently
in different calls.

A POST upload requires confirmation of operation safety through Retry.safe or
RetryConfig.safeMethods; enabling retry alone is insufficient. See the
[retry contract](../execution/retry.md).
Binary and multipart are sent as streams. The file's starting point is captured from
its current position when preparing execution; the adapter cannot access earlier
bytes even after rewind. A new `send()` starts a new execution at the source's current
position: rewind it beforehand if you need the whole file. `FileInput::fromPath()`
opens the file at its beginning.

### Upload formats <a id="section-4"></a>
- **Multipart**: standard `multipart/form-data`.
- **Binary**: one file sent as a raw body; MIME comes from `FileInput`.
- **Base64**: the file is base64-encoded and placed in the JSON body under its field name.

Binary example:
```php
#[File('file', format: FileFormat::Binary)]
public FileInput $file;
```

Base64 example:
```php
#[File('file', format: FileFormat::Base64)]
public FileInput $file;
```

### Multiple files <a id="section-5"></a>
```php
#[File('files')]
public array $files;
```
An array containing several `FileInput` objects is sent as a set of files.

### FileInput factories <a id="section-6"></a>
- `fromPath()`: from a file; throws `ConfigurationException` on failure.
- `tryFromPath()`: from a file, with a `?FileInput` return type on failure for exception-free path handling.
- `fromContent()`: from a string.
- `fromStream()`: from a PSR-7 stream.
- `withMimeType()`: overrides the MIME type.
- `withFilename()`: returns a copy with a different filename, preserving MIME, size and stream position.
- `close()`: closes the handle created by `fromPath()`/`fromContent()`; a stream borrowed
  through `fromStream()` is not closed. `withMimeType()` and `withFilename()` copies share the handle.

For a path supplied by a user/form, use `tryFromPath()` and add a `ValidationError`
in `validateCustom()` if it returns `null`.
