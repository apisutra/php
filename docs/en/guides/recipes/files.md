<!-- languages --> <a href="files.md">English</a> · <a href="../../../ru/guides/recipes/files.md">Русский</a> <!-- /languages -->
# Files and archives <a id="section-1"></a>

This recipe uploads a document in three ways, downloads it to a path and a stream,
then reads a file from a TAR archive. All operations run without network access:

```bash
php docs/example/files/run.php
```

Run the command from a checkout after `composer install`. In an installed package,
prefix the path with `vendor/apisutra/php/`. TAR requires `ext-phar`.
Complete client assembly, local responses, and temporary-file cleanup are in
[run.php](../../../example/files/run.php); results are in [expected.json](../../../example/files/fixtures/expected.json).

## Choose an upload format <a id="section-2"></a>

| What the API accepts | Declaration | Request body |
| --- | --- | --- |
| A file together with ordinary fields | `FileFormat::Multipart` and `Body` | multipart/form-data; file bytes are streamed |
| Only the contents of one file | `FileFormat::Binary` | Raw body from a stream; MIME comes from FileInput |
| A file inside JSON | `FileFormat::Base64` | A Base64 string under the field name; the body is materialized in memory |

Open a source with `FileInput::fromPath()`, create it from a string using
`fromContent()`, or pass a PSR-7 stream through `fromStream()`.
See the [complete format and source contract](../../reference/files/uploads.md).

## Upload a file with a description <a id="section-3"></a>

[MultipartUploadRequest](../../../example/files/src/Resources/Files/MultipartUploadRequest.php)
declares a `document` file field and a `description` text field:

```php
declare(strict_types=1);

namespace Example\Files\Resources\Files;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\VO\Files\FileInput;

#[Post('/files')]
final class MultipartUploadRequest extends AbstractRequest
{
    public function __construct(
        #[File('document', format: FileFormat::Multipart)]
        public FileInput $document,
        #[Body('description')]
        public string $description,
    ) {
    }
}
```

The following snippet assumes `$client` from [run.php](../../../example/files/run.php)
and an existing document path `$path`:

```php
use ApiSutra\VO\Files\FileInput;
use Example\Files\Resources\Files\MultipartUploadRequest;

$input = FileInput::fromPath($path);
try {
    $result = $client->send(new MultipartUploadRequest($input, 'Monthly report'))->dataOrFail();
} finally {
    $input->close();
}
```

The tutorial response is `['id' => 7]`. Similar requests,
[BinaryUploadRequest](../../../example/files/src/Resources/Files/BinaryUploadRequest.php) and
[Base64UploadRequest](../../../example/files/src/Resources/Files/Base64UploadRequest.php),
show the other formats. The example reopens the source for every call.
POST is retried only when operation safety is explicitly permitted;
see [file retries and stream positions](../../reference/files/uploads.md#section-3).

## Download to a file <a id="section-4"></a>

[DownloadFileRequest](../../../example/files/src/Resources/Files/DownloadFileRequest.php)
declares `Get('/files/{id}')`, `Path` for id, and `Download` for a `FileResponse` result.
Here, `$directory` is an existing temporary directory created in run.php:

```php
use Example\Files\Resources\Files\DownloadFileRequest;

$file = $client->send(
    (new DownloadFileRequest(7))->withDownloadTo($directory . '/report.txt'),
)->dataOrFail();
try {
    echo $file->filename(); // report.txt
    echo $file->size(); // 10 bytes
} finally {
    $file->close();
}
```

`withDownloadTo()` publishes the file after successfully receiving the response.
Existing paths are protected against overwriting; pass `overwrite: true` for explicit replacement.
Without a destination, the SDK returns `FileResponse` backed by a temporary file;
you can call `saveTo($path)` later.
See [paths, overwriting, and resource ownership](../../reference/files/downloads.md#section-4).

## Download to your own stream <a id="section-5"></a>

The same `$client` can write the result to a writable PSR-7 stream:

```php
use Example\Files\Resources\Files\DownloadFileRequest;
use GuzzleHttp\Psr7\Utils;

$sink = Utils::streamFor('');
$file = null;
try {
    $file = $client->send((new DownloadFileRequest(7))->withDownloadTo($sink))->dataOrFail();
    $sink->rewind();
    echo $sink->getContents(); // Report #7 followed by a newline
} finally {
    $file?->close();
    $sink->close();
}
```

The caller owns a user-provided stream. Closing `FileResponse` does not close `$sink`;
the SDK writes from the destination's current position.
For large files, process the stream in chunks: `content()` reads the entire response.

## Open a downloaded archive <a id="section-6"></a>

In run.php, the next fake response contains [report.tar](../../../example/files/fixtures/report.tar)
with MIME `application/x-tar`. After configuring that response:

```php
use Example\Files\Resources\Files\DownloadFileRequest;

$file = $client->send(new DownloadFileRequest(8))->dataOrFail();
try {
    if ($file->isArchive()) {
        $archive = $file->asArchive();
        $entry = $archive->get('report.txt');
        $entry?->saveTo($directory . '/extracted.txt');
    }
} finally {
    unset($entry, $archive);
    $file->close();
}
```

`asArchive()` materializes the archive in memory. ZIP requires `ext-zip`; TAR requires
`ext-phar`. Selecting an entry, extracting all files, and automatic processing
through ArchiveExtension are covered in the [archive reference](../../reference/files/archives.md).

## A file inside a DTO <a id="section-7"></a>

If the API returns file contents as a string inside JSON, use
[Base64File and DataUriBase64FileCast](../dto/showcase.md#section-7).
The DTO showcase demonstrates reading the field and serializing it back into JSON.
`File` describes an outgoing request file parameter; `Download` describes an entire file response.

[All executable examples](../../examples/README.md) · [File reference](../../reference/files/README.md).
