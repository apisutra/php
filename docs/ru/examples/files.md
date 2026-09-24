<!-- languages --> <a href="../../en/examples/files.md">English</a> · <a href="files.md">Русский</a> <!-- /languages -->
# Файлы и архивы без сети <a id="section-1"></a>

Учебный API принимает документ тремя способами и возвращает файл или TAR-архив.
Пример проходит настоящий pipeline SDK с MockTransport: подменённые ответы берутся
из локальных фикстур, исходящие запросы записываются для просмотра результата.

## Запуск <a id="section-2"></a>

После `composer install` из checkout:

```bash
php docs/example/files/run.php
```

В проекте с установленным пакетом:

```bash
php vendor/apisutra/php/docs/example/files/run.php
```

Нужны PHP 8.4+, Composer autoload и `ext-phar` для чтения TAR.
Пример создаёт уникальный каталог в системном временном каталоге, сохраняет туда
скачанный документ и элемент архива, затем закрывает потоки и удаляет свои файлы.
Настройки сети, Laravel и ключи API не требуются.

## Результат <a id="section-3"></a>

| Часть вывода | Что проверяется |
| --- | --- |
| `uploads.multipart` | Файл document и текст description в отдельных частях; MIME, имя и байты файла |
| `uploads.binary` | Raw body содержит байты report.txt и отправляется потоком с MIME text/plain |
| `uploads.base64` | JSON содержит document со строкой `UmVwb3J0ICM3Cg==` |
| `downloads` | Имя report.txt, размер 10 байт, одинаковое содержимое в пути и пользовательском потоке |
| `archive` | TAR содержит report.txt; прочитанный и сохранённый элемент равны исходной фикстуре |

[expected.json](../../example/files/fixtures/expected.json) задаёт результат заранее. Случайный multipart boundary
в выводе заменён на `EXAMPLE_BOUNDARY`; остальные байты запроса сохранены.
Чтение upload-потоков целиком нужно только для показа маленькой учебной фикстуры.
Реальный HTTP и расход памяти большого файла этим примером не измеряются.

## Исходники <a id="section-4"></a>

| Файл | Назначение |
| --- | --- |
| [run.php](../../example/files/run.php) | Клиент, fake-ответы, отправка, скачивание и чтение архива |
| [FilesClient](../../example/files/src/FilesClient.php) | Минимальный клиент SDK |
| [MultipartUploadRequest](../../example/files/src/Resources/Files/MultipartUploadRequest.php) | File вместе с текстовым Body |
| [BinaryUploadRequest](../../example/files/src/Resources/Files/BinaryUploadRequest.php) | Один файл в raw body |
| [Base64UploadRequest](../../example/files/src/Resources/Files/Base64UploadRequest.php) | Кодирование файла в JSON |
| [DownloadFileRequest](../../example/files/src/Resources/Files/DownloadFileRequest.php) | Path и Download для получения FileResponse |
| [report.txt](../../example/files/fixtures/report.txt), [report.tar](../../example/files/fixtures/report.tar) | Файл `Report #7` с переводом строки и TAR с этим же файлом |

[Пошаговый рецепт](../guides/recipes/files.md) · [Справочник файлов](../reference/files/README.md) ·
[Base64-поле DTO](../guides/dto/showcase.md#section-7) · [Все примеры](README.md).
