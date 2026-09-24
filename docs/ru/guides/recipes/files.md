<!-- languages --> <a href="../../../en/guides/recipes/files.md">English</a> · <a href="files.md">Русский</a> <!-- /languages -->
# Файлы и архивы <a id="section-1"></a>

Этот рецепт загружает документ тремя способами, скачивает его в путь и поток,
затем читает файл из TAR-архива. Все операции воспроизводятся без сети:

```bash
php docs/example/files/run.php
```

Команда выполняется из checkout после `composer install`. В установленном пакете
добавьте к пути `vendor/apisutra/php/`. Для TAR нужен `ext-phar`.
Полная сборка клиента, локальные ответы и очистка временных файлов находятся в
[run.php](../../../example/files/run.php), результаты — в [expected.json](../../../example/files/fixtures/expected.json).

## Выбрать формат загрузки <a id="section-2"></a>

| Что принимает API | Декларация | Тело запроса |
| --- | --- | --- |
| Файл вместе с обычными полями | `FileFormat::Multipart` и `Body` | multipart/form-data; файловые байты передаются потоком |
| Только содержимое одного файла | `FileFormat::Binary` | Raw body из потока, MIME берётся из FileInput |
| Файл внутри JSON | `FileFormat::Base64` | Строка Base64 по имени поля; тело материализуется в памяти |

Исходник можно открыть через `FileInput::fromPath()`, получить из строки через
`fromContent()` или передать PSR-7 поток через `fromStream()`.
[Полный контракт форматов и источников](../../reference/files/uploads.md).

## Загрузить файл с описанием <a id="section-3"></a>

[MultipartUploadRequest](../../../example/files/src/Resources/Files/MultipartUploadRequest.php)
объявляет файловое поле `document` и текстовое `description`:

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

Фрагмент ниже предполагает `$client` из [run.php](../../../example/files/run.php)
и существующий путь `$path` к документу:

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

Учебный ответ — `['id' => 7]`. Аналогичные запросы
[BinaryUploadRequest](../../../example/files/src/Resources/Files/BinaryUploadRequest.php) и
[Base64UploadRequest](../../../example/files/src/Resources/Files/Base64UploadRequest.php)
показывают другие форматы. Для каждого вызова пример открывает источник заново.
POST повторяется только при явно разрешённой безопасности операции;
[повторы файлов и позиции потоков](../../reference/files/uploads.md#section-3).

## Скачать в файл <a id="section-4"></a>

[DownloadFileRequest](../../../example/files/src/Resources/Files/DownloadFileRequest.php)
объявляет `Get('/files/{id}')`, `Path` для id и `Download` для результата `FileResponse`.
Здесь `$directory` — существующий временный каталог, созданный в run.php:

```php
use Example\Files\Resources\Files\DownloadFileRequest;

$file = $client->send(
    (new DownloadFileRequest(7))->withDownloadTo($directory . '/report.txt'),
)->dataOrFail();
try {
    echo $file->filename(); // report.txt
    echo $file->size(); // 10 байт
} finally {
    $file->close();
}
```

`withDownloadTo()` публикует файл после успешного получения ответа. Существующий путь
защищён от перезаписи; для явной замены передайте `overwrite: true`.
Без цели SDK вернёт `FileResponse` с временным файлом; позднее можно вызвать `saveTo($path)`.
[Пути, перезапись и владение ресурсами](../../reference/files/downloads.md#section-4).

## Скачать в свой поток <a id="section-5"></a>

Тот же `$client` может записать результат в writable PSR-7 поток:

```php
use Example\Files\Resources\Files\DownloadFileRequest;
use GuzzleHttp\Psr7\Utils;

$sink = Utils::streamFor('');
$file = null;
try {
    $file = $client->send((new DownloadFileRequest(7))->withDownloadTo($sink))->dataOrFail();
    $sink->rewind();
    echo $sink->getContents(); // Report #7 с переводом строки
} finally {
    $file?->close();
    $sink->close();
}
```

Пользовательский поток принадлежит вызывающему коду. Закрытие `FileResponse`
не закрывает `$sink`; SDK записывает с текущей позиции приёмника.
Для больших файлов обрабатывайте поток порциями: `content()` читает ответ целиком.

## Открыть скачанный архив <a id="section-6"></a>

В run.php следующий fake-ответ содержит [report.tar](../../../example/files/fixtures/report.tar)
с MIME `application/x-tar`. После такой настройки:

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

`asArchive()` материализует архив в памяти. Для ZIP требуется `ext-zip`, для TAR —
`ext-phar`. Выбор элемента, извлечение всех файлов и автоматическая обработка
через ArchiveExtension описаны в [справочнике архивов](../../reference/files/archives.md).

## Файл внутри DTO <a id="section-7"></a>

Если API возвращает содержимое файла строкой внутри JSON, используйте
[Base64File и DataUriBase64FileCast](../dto/showcase.md#section-7).
Витрина DTO показывает чтение поля и обратную сериализацию в JSON.
`File` описывает исходящий файловый параметр запроса; `Download` — файловый ответ целиком.

[Все исполняемые примеры](../../examples/README.md) · [Справочник файлов](../../reference/files/README.md).
