<!-- languages --> <a href="../../../en/reference/files/archives.md">English</a> · <a href="archives.md">Русский</a> <!-- /languages -->
# Распаковка архивов <a id="section-1"></a>

Настройки архивирования файлов через `ArchiveConfig`.

## Базовая настройка <a id="section-2"></a>
```php
use ApiSutra\Config\ArchiveConfig;
use ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    archive: new ArchiveConfig(driver: 'auto'),
);
```

## Параметры ArchiveConfig <a id="section-3"></a>
- `driver`: `native` | `spatie` | `auto`
- `tempDir`: базовая директория для временных файлов
- `maxSize`: лимит размера архива (в байтах)
- `tempProvider`: собственный провайдер временных директорий

Поведение по умолчанию:
- без `ArchiveConfig` используется `native`
- `auto` выберет `spatie`, если пакет установлен, иначе `native`

`maxSize` проверяется при подготовке временного файла. Если архив превышает лимит,
будет `ConfigurationException`.

`tempProvider` имеет приоритет над `driver` и `tempDir`.

## Кастомный tempProvider <a id="section-4"></a>
Используйте, если нужно строго контролировать директорию, cleanup,
или окружение ограничено (read-only, нестандартные tmp‑пути).
Если требований нет — достаточно `native`/`auto`.

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

## Зависимости <a id="section-5"></a>
- `driver = spatie` требует пакет `spatie/temporary-directory`
- `auto` выберет spatie при наличии, иначе native

## Архивы <a id="section-6"></a>
Если ответы приходят архивом, можно подключить архивное расширение.
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Extensions\Archive\ArchiveExtension;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    extensions: [new ArchiveExtension()],
);
```

Также доступны `FileResponse::isArchive()` и `FileResponse::asArchive()`.

По умолчанию (без `ArchiveExtension`) архив возвращается как обычный `FileResponse`.
Этого достаточно, если вы сами проверяете `isArchive()` и вызываете `asArchive()`.
`ArchiveExtension` нужен, когда вы хотите автоматическую обработку архивов
по `Content-Type` через handlers.

`ArchiveResponse` позволяет:
- `list()` / `get()` / `first()` / `find()` / `each()` / `extractAll()`

`ArchiveEntry` поддерживает:
- `contents()` / `stream()` / `saveTo()`

Пример работы с архивом:
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

Нюансы:
- Для zip нужен `ext-zip`, для tar — `ext-phar`.
- Временные директории управляются через `ArchiveConfig` (см. ниже).
