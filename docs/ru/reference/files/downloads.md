<!-- languages --> <a href="../../../en/reference/files/downloads.md">English</a> · <a href="downloads.md">Русский</a> <!-- /languages -->
# Скачивание файлов <a id="section-1"></a>

## Скачивание файлов <a id="section-2"></a>
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

`FileResponse` поддерживает:
- `content()` / `stream()`
- `filename()` / `mimeType()` / `size()`
- `isArchive()` / `asArchive()`
- `saveTo()` / `close()`

### Потоковый download без дополнительных настроек <a id="section-3"></a>

`#[Download]` автоматически получает ответ во временный файл на диске. В памяти
находятся только порции данных; `FileResponse` доступен после завершения HTTP.
Размер буфера и каталог настраивать не нужно. В системном временном каталоге
должно быть достаточно места для ответа и одновременно сохраняемых результатов.

`stream()` возвращает читаемый поток с начальной позицией 0, `size()` — фактическое
число полученных байтов, включая корректный размер пустого файла 0. `content()`
явно читает весь файл в строку и требует соответствующей памяти. `asArchive()`
также материализует содержимое; потоковый download не делает разбор архивов потоковым.

### Сохранение сразу в путь или поток <a id="section-4"></a>

```php
$file = (new DownloadFile(10))
    ->withDownloadTo('/tmp/report.pdf')
    ->send()
    ->dataOrFail();
```

Новый `withDownloadTo()` защищает существующий файл: замена разрешается только через
`overwrite: true`. Принимается локальный путь; каталог должен существовать.
Stream wrappers, конечные symlink и каталог вместо файла отклоняются до HTTP.
Имя из `Content-Disposition` не используется как путь назначения.

Сначала SDK получает файл во временный файл рядом с назначением. После окончательного
принятого 2xx, hooks и проверки бюджета он атомарно публикует результат. Неудачный
HTTP, отказ hook или retry не затирают прежний файл и не смешивают тела попыток.
Публикация без overwrite не заменяет и файл, появившийся во время запроса.
Если файловая система не поддерживает атомарную операцию, возвращается ошибка.
Это не гарантия сохранности при аппаратном сбое.

```php
use GuzzleHttp\Psr7\Utils;

$sink = Utils::streamFor(fopen('/tmp/report-copy.pdf', 'w+b'));
try {
    $file = (new DownloadFile(10))->withDownloadTo($sink)->send()->dataOrFail();
} finally {
    $sink->close();
}
```

Для пользовательского `StreamInterface` требуется writable поток. SDK копирует
только окончательный успешный ответ с текущей позиции приёмника, не перематывает,
не усекает хвост и не закрывает его. Write-only и non-seekable приёмники допустимы;
`overwrite` к потоку неприменим. Если ошибка возникла при финальном копировании,
приёмник может содержать часть файла. Ошибка сообщает `bytesWritten` и `partial`;
HTTP из-за локальной ошибки записи не повторяется.

Результат остаётся `FileResponse` с собственным читаемым потоком, в том числе для
write-only приёмника. `withoutDownloadTo()` снимает цель и возвращает обычный download.
Эти runtime-опции применимы только к запросам с `#[Download]` и не наследуются
дочерними запросами.

Существующий `FileResponse::saveTo()` сохраняет разрешённую перезапись, но тоже
проверяет запись и публикует файл атомарно. Для seekable источника читает с начала;
для non-seekable — с текущей позиции. `isArchive()` не читает magic bytes из
non-seekable потока, чтобы не потерять первые байты; MIME остаётся доступным.

### Владение ресурсами, ошибки и диагностика <a id="section-5"></a>

SDK не закрывает пользовательские upload/sink потоки. Ручки `FileInput`, созданные
фабриками SDK, остаются доступными после отправки, пока их владелец жив; для раннего
освобождения используйте `close()`. Повторное использование требует контроля позиции.

`FileResponse` и raw response разделяют поток. Освобождение одной ссылки не закрывает
его для другой. Явный `FileResponse::close()` закрывает общий поток; последний владелец
также освобождает временный файл. Конечный сохранённый файл при этом не удаляется.
В сохранённом ошибочном raw-ответе поток остаётся доступным для диагностики.

HTTP-таймауты охватывают получение тела; общий бюджет также проверяется при копировании
и перед публикацией. Отдельный блокирующий вызов произвольного пользовательского потока
SDK не может принудительно прервать. Локальные ошибки имеют `file_transfer_error`,
истечение бюджета — `timeout`. Ошибка финального logger после сохранения не отменяет
успех файловой операции.

Файловые upload/download исключены из HTTP-кеша автоматически. Явное включение
`withCache()`/`#[Cache]` даёт `configuration_error` до HTTP; `withoutCache()` снимает
конфликт. Это относится и к upload с `FileInput` в Base64. Обычные JSON-строки
не считаются файлами только из-за похожего содержимого.

Debug и recorder показывают метаданные потока, не читают файл и не меняют его позицию.
Даже `requestDebug(false)` не создаёт строковую копию. Для воспроизведения файлов
используйте [ручную файловую fixture](../testing/mocking.md#section-4).

### Замена файлового тела в hook <a id="section-6"></a>

`withBody($json)` заменяет поток строкой, `withStream($stream)` выбирает новый поток,
`withoutBody()` удаляет всё исходящее тело. При multipart → JSON явно задайте
`Content-Type: application/json`. Старые Content-Length/Transfer-Encoding убираются
автоматически, исходный поток остаётся открытым. Очистка upload не включает HTTP-кеш
и не сбрасывает download target, назначение или общий бюджет. Поток, добавленный hook,
требует `FileStreamingInterface` даже при исходном запросе без файла.
Полный контракт и пример — [управление телом](../execution/transport.md#section-3).

### Представление потоков и ответов <a id="section-7"></a>

- Binary находится в `PreparedRequest.stream`, `body = null`; учитывайте это
  в собственных hooks/адаптерах. Ненулевая позиция сохраняется.
- Non-seekable binary не копируется в строку: повтор запрещён.
- У download `ProviderResponse.body = null`, полный ответ доступен в `stream`.
  `json()`/`jsonStrict()` на таком ответе отклоняются вместо неявного чтения файла.
  Для обычных JSON/text ответов строковый контракт сохранён.
- `errorMessage()` читает JSON-сообщение потокового ответа только если всё тело
  укладывается в 64 KiB, восстанавливая позицию. Для большего тела используется
  `HTTP <status>`; полный raw-поток сохраняется.
- Явный HTTP cache файлов не поддерживается. Сторонним транспортам,
  PSR-клиентам и retry handler нужен [контракт FileStreamingInterface](../execution/transport.md#section-4).
- Base64 JSON, явные `content()`/`asArchive()` и обычные ответы без `#[Download]`
  остаются операциями с полной материализацией. Универсальный лимит JSON не вводится.

## Base64 в ответе <a id="section-8"></a>
Работающий DTO с входным data URI и обратной сериализацией показан в
[витрине DTO](../../guides/dto/showcase.md#section-7).

Если поле DTO типизировано как `Base64File`, SDK автоматически декодирует строку:
```php
use ApiSutra\VO\Files\Base64File;

public Base64File $document;
```

Важно:
- встроенный `Base64File` ожидает **чистую base64-строку**
- если провайдер возвращает data-uri вида `data:image/jpeg;base64,...`, используйте явный `DataUriBase64FileCast`
- если приходит `list<data-uri-string>`, используйте `Nested(type: Base64File::class, itemCast: DataUriBase64FileCast::class)`

Пример:
```php
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Casts\DataUriBase64FileCast;
use ApiSutra\VO\Files\Base64File;

#[Cast(DataUriBase64FileCast::class)]
public ?Base64File $photo = null;
```

`DataUriBase64FileCast`:
- понимает и чистый base64
- и data-uri с префиксом `data:...;base64,`
- в `Base64File` передаёт уже нормализованный base64 payload
