<!-- languages --> <a href="../../../en/reference/files/uploads.md">English</a> · <a href="uploads.md">Русский</a> <!-- /languages -->
# Загрузка файлов <a id="section-1"></a>

Форматы и источники исходящего файлового тела. Полная сборка клиента и запуск —
в [практическом примере](../../guides/recipes/files.md).

## Загрузка файлов <a id="section-2"></a>
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

Поддерживаемые форматы: `Multipart`, `Binary`, `Base64`.

В именах файлов multipart символы `"`, CR и LF кодируются как `%22`, `%0D` и `%0A`
перед записью заголовка части, предотвращая внедрение заголовков и подмену поля через
имя файла. Это не меняет `FileInput::filename`, байты файла, Unicode-символы
и буквальные знаки процента.

### Повторная отправка <a id="section-3"></a>

При разрешённом retry ядро автоматически восстанавливает seekable-поток до исходной
позиции, которая может быть ненулевой. Для multipart сохраняются позиции файловых
частей, boundary и байты тела. Никаких настроек перемотки не требуется; SDK не закрывает
переданный пользователем поток. Пустой файл остаётся допустимым.

Non-seekable поток допускает первую отправку, но не повторную. При невозможном tell/seek
или замене тела после отправки SDK прекращает повтор, сохраняя исходную ошибку и
`retryRefusalReason` в её контексте. Неявная буферизация и фабрики повторяемых потоков
не применяются. Гарантия повторяемости относится к попыткам одного выполнения;
не меняйте содержимое файла извне и не используйте один поток одновременно в разных вызовах.

POST-загрузка требует подтверждения безопасности операции через Retry.safe или
RetryConfig.safeMethods; одного включения retry недостаточно. См.
[контракт повторов](../execution/retry.md).
Binary и multipart передаются потоками. Начало файла фиксируется по текущей позиции
при подготовке выполнения; адаптер не получает байты до этой позиции даже при rewind.
Повторный `send()` начинает новое выполнение с текущей позиции исходника: если нужен
весь файл, перемотайте его заранее. `FileInput::fromPath()` открывает файл с начала.

### Форматы загрузки <a id="section-4"></a>
- **Multipart** — стандартный `multipart/form-data`.
- **Binary** — один файл отправляется как raw body; MIME берётся из `FileInput`.
- **Base64** — файл кодируется в base64 и кладётся в JSON‑body по имени поля.

Пример Binary:
```php
#[File('file', format: FileFormat::Binary)]
public FileInput $file;
```

Пример Base64:
```php
#[File('file', format: FileFormat::Base64)]
public FileInput $file;
```

### Несколько файлов <a id="section-5"></a>
```php
#[File('files')]
public array $files;
```
Если в массиве несколько `FileInput`, они будут отправлены как набор файлов.

### FileInput фабрики <a id="section-6"></a>
- `fromPath()` — из файла (бросает `ConfigurationException` при ошибке)
- `tryFromPath()` — из файла, возвращает `?FileInput` при ошибке (для path-flow без исключений)
- `fromContent()` — из строки
- `fromStream()` — из PSR‑7 stream
- `withMimeType()` — переопределить MIME‑тип
- `withFilename()` — копия с другим именем файла, сохраняющая MIME, размер и позицию потока
- `close()` — закрыть ручку, созданную `fromPath()`/`fromContent()`; заимствованный
  через `fromStream()` поток не закрывается. Копии `withMimeType()` и `withFilename()` разделяют ручку.

Для пути, приходящего от пользователя/формы, используйте `tryFromPath()` и при `null` добавляйте `ValidationError` в `validateCustom()`.
