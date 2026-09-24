<!-- languages --> <a href="extensions.md">English</a> · <a href="../../ru/glossary/extensions.md">Русский</a> <!-- /languages -->
# Extensions <a id="section-1"></a>

| Term | Meaning | Details |
| --- | --- | --- |
| <a id="extensioninterface"></a> ExtensionInterface | The SDK extension contract. | [Contract](../reference/extensions/extensions.md) |
| <a id="extensionregistry"></a> ExtensionRegistry | A per-client extension registry. | [Contract](../reference/extensions/extensions.md) |
| <a id="extensioncontext"></a> ExtensionContext | The context for registering extension components. | [Contract](../reference/extensions/extensions.md) |
| <a id="responsehandlerinterface"></a> ResponseHandlerInterface | The interface for a MIME-based response handler. | [Contract](../reference/extensions/extensions.md) |
| <a id="archiveextension"></a> ArchiveExtension | A built-in extension for archives (ZIP, TAR). | [Contract](../reference/files/archives.md) |
| <a id="archiveresponse"></a> ArchiveResponse | A response wrapper for archives. | [Contract](../reference/files/archives.md) |
| <a id="archiveentry"></a> ArchiveEntry | A value object for a file inside an archive. | [Contract](../reference/files/archives.md) |
| <a id="tempdirectoryproviderinterface"></a> TempDirectoryProviderInterface | An internal contract for creating and cleaning up temporary archive files. | [Contract](../reference/files/archives.md) |
| <a id="extensionconflictexception"></a> ExtensionConflictException | An exception raised when registering a handler for an occupied MIME type without override: true. | [Contract](../reference/results/errors.md) |
| <a id="extensiondisabledexception"></a> ExtensionDisabledException | An exception raised when using a disabled extension (failed checkDependencies). | [Contract](../reference/results/errors.md) |

[All terms](README.md).
