<!-- languages --> <a href="files.md">English</a> · <a href="../../ru/examples/files.md">Русский</a> <!-- /languages -->
# Files and archives without network access <a id="section-1"></a>

The example API accepts a document in three ways and returns a file or TAR archive. The example runs the actual SDK pipeline with MockTransport: substitute responses come from local fixtures, and outgoing requests are recorded for inspection.

## Run <a id="section-2"></a>

From the checkout after `composer install`:

```bash
php docs/example/files/run.php
```

From a project with the package installed:

```bash
php vendor/apisutra/php/docs/example/files/run.php
```

Requires PHP 8.4+, Composer autoload, and `ext-phar` to read TAR. The example creates a unique directory in the system temporary directory, saves the downloaded document and an archive entry there, then closes streams and removes its files. Network settings, Laravel, and API keys are unnecessary.

## Result <a id="section-3"></a>

| Output section | What is checked |
| --- | --- |
| `uploads.multipart` | The document file and description text in separate parts; file MIME, name, and bytes |
| `uploads.binary` | The raw body contains report.txt bytes and is sent as a stream with MIME text/plain |
| `uploads.base64` | JSON contains document with the string `UmVwb3J0ICM3Cg==` |
| `downloads` | Name report.txt, size 10 bytes, identical content at the path and in the user stream |
| `archive` | TAR contains report.txt; the read and saved entry match the original fixture |

[expected.json](../../example/files/fixtures/expected.json) defines the result in advance. The random multipart boundary is replaced with `EXAMPLE_BOUNDARY` in the output; other request bytes are preserved. Reading entire upload streams is only for displaying the small teaching fixture. This example does not measure real HTTP or memory usage for large files.

## Source files <a id="section-4"></a>

| File | Purpose |
| --- | --- |
| [run.php](../../example/files/run.php) | Client, fake responses, sending, downloading, and reading an archive |
| [FilesClient](../../example/files/src/FilesClient.php) | Minimal SDK client |
| [MultipartUploadRequest](../../example/files/src/Resources/Files/MultipartUploadRequest.php) | File alongside text Body |
| [BinaryUploadRequest](../../example/files/src/Resources/Files/BinaryUploadRequest.php) | One file in the raw body |
| [Base64UploadRequest](../../example/files/src/Resources/Files/Base64UploadRequest.php) | File encoding into JSON |
| [DownloadFileRequest](../../example/files/src/Resources/Files/DownloadFileRequest.php) | Path and Download to obtain FileResponse |
| [report.txt](../../example/files/fixtures/report.txt), [report.tar](../../example/files/fixtures/report.tar) | The file `Report #7` with a newline, and TAR containing the same file |

[Step-by-step recipe](../guides/recipes/files.md) · [File reference](../reference/files/README.md) · [DTO Base64 field](../guides/dto/showcase.md#section-7) · [All examples](README.md).
