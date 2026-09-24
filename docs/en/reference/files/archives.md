<!-- languages --> <a href="archives.md">English</a> · <a href="../../../ru/reference/files/archives.md">Русский</a> <!-- /languages -->
# Extracting archives <a id="section-1"></a>

Configure file archive handling through `ArchiveConfig`.

## Basic configuration <a id="section-2"></a>
```php
use ApiSutra\Config\ArchiveConfig;
use ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    archive: new ArchiveConfig(driver: 'auto'),
);
```

## ArchiveConfig parameters <a id="section-3"></a>
- `driver`: `native` | `spatie` | `auto`.
- `tempDir`: the base directory for temporary files.
- `maxSize`: the archive size limit, in bytes.
- `tempProvider`: a custom temporary directory provider.

Default behavior:
- Without `ArchiveConfig`, `native` is used.
- `auto` selects `spatie` if the package is installed, otherwise `native`.

`maxSize` is checked when preparing the temporary file. An archive exceeding the
limit causes `ConfigurationException`.

`tempProvider` takes precedence over `driver` and `tempDir`.

## Custom tempProvider <a id="section-4"></a>
Use this for strict control over the directory or cleanup, or in a restricted
environment (read-only filesystem, nonstandard temporary paths).
Otherwise, `native`/`auto` is sufficient.

```php
use ApiSutra\Extensions\Archive\Temp\TempDirectoryProviderInterface;

final class ProviderTempDirectory implements TempDirectoryProviderInterface
{
    public function createTempFile(?string $suffix = null): string
    {
        $name = $suffix ? 'archive_' . $suffix : 'archive.tmp';
        return sys_get_temp_dir() . '/' . $name;
    }

    public function cleanup(string $path): void
    {
        @unlink($path);
    }
}

$config = $config->with(
    archive: new ArchiveConfig(
        tempProvider: new ProviderTempDirectory(),
        maxSize: 50 * 1024 * 1024,
    ),
);
```

## Dependencies <a id="section-5"></a>
- `driver = spatie` requires `spatie/temporary-directory`.
- `auto` selects spatie when available, otherwise native.

## Archives <a id="section-6"></a>
If responses contain archives, you can attach the archive extension.
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Extensions\Archive\ArchiveExtension;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    extensions: [new ArchiveExtension()],
);
```

`FileResponse::isArchive()` and `FileResponse::asArchive()` are also available.

By default, without `ArchiveExtension`, an archive is returned as an ordinary
`FileResponse`. This is sufficient if you check `isArchive()` and call `asArchive()`
yourself. Use `ArchiveExtension` for automatic archive processing through handlers
selected by `Content-Type`.

`ArchiveResponse` provides:
- `list()` / `get()` / `first()` / `find()` / `each()` / `extractAll()`.

`ArchiveEntry` supports:
- `contents()` / `stream()` / `saveTo()`.

Archive example:
```php
$file = (new DownloadFile(10))->send()->dataOrFail();
if ($file->isArchive()) {
    $archive = $file->asArchive();
    $entry = $archive->first();
    if ($entry !== null) {
        $entry->saveTo('/tmp/first.pdf');
    }
}
```

Details:
- ZIP requires `ext-zip`; TAR requires `ext-phar`.
- Temporary directories are managed through `ArchiveConfig` (see above).
