<!-- languages --> <a href="../../en/glossary/extensions.md">English</a> · <a href="extensions.md">Русский</a> <!-- /languages -->
# Extensions <a id="section-1"></a>

| Термин | Значение | Подробнее |
| --- | --- | --- |
| <a id="extensioninterface"></a> ExtensionInterface | Контракт расширения SDK. | [Контракт](../reference/extensions/extensions.md) |
| <a id="extensionregistry"></a> ExtensionRegistry | Хранилище расширений per-client. | [Контракт](../reference/extensions/extensions.md) |
| <a id="extensioncontext"></a> ExtensionContext | Контекст для регистрации компонентов расширения. | [Контракт](../reference/extensions/extensions.md) |
| <a id="responsehandlerinterface"></a> ResponseHandlerInterface | Интерфейс обработчика response по MIME. | [Контракт](../reference/extensions/extensions.md) |
| <a id="archiveextension"></a> ArchiveExtension | Built-in расширение для работы с архивами (ZIP, TAR). | [Контракт](../reference/files/archives.md) |
| <a id="archiveresponse"></a> ArchiveResponse | Response-обёртка для архивов. | [Контракт](../reference/files/archives.md) |
| <a id="archiveentry"></a> ArchiveEntry | Value Object файла в архиве. | [Контракт](../reference/files/archives.md) |
| <a id="tempdirectoryproviderinterface"></a> TempDirectoryProviderInterface | Внутренний контракт для создания/очистки temp‑файлов архивов. | [Контракт](../reference/files/archives.md) |
| <a id="extensionconflictexception"></a> ExtensionConflictException | Исключение при попытке зарегистрировать handler на уже занятый MIME без флага override: true. | [Контракт](../reference/results/errors.md) |
| <a id="extensiondisabledexception"></a> ExtensionDisabledException | Исключение при попытке использовать отключённое расширение (failed checkDependencies). | [Контракт](../reference/results/errors.md) |

[Все термины](README.md).
