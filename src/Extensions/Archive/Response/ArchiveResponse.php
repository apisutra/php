<?php

declare(strict_types=1);

namespace ApiSutra\Extensions\Archive\Response;

use ApiSutra\Localization\Message;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Extensions\Archive\Temp\ArchiveTempFileManager;
use ApiSutra\Extensions\Archive\Temp\TempDirectoryProviderInterface;
use DateTimeImmutable;
use PharData;
use PharFileInfo;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

final class ArchiveResponse
{
    private ?ZipArchive $zip = null;
    private ?PharData $phar = null;
    private ?string $tempPath = null;
    private ?ArchiveTempFileManager $tempManager = null;

    public function __construct(
        private readonly string $content,
        private readonly string $format,
        private readonly ?ClientConfig $config = null,
        private readonly ?TempDirectoryProviderInterface $tempProvider = null,
    ) {
    }

    /**
     * @return array<ArchiveEntry>
     */
    public function list(): array
    {
        return $this->format === 'zip'
            ? $this->listZip()
            : $this->listPhar();
    }

    public function has(string $name): bool
    {
        return $this->get($name) !== null;
    }

    public function get(string $name): ?ArchiveEntry
    {
        foreach ($this->list() as $entry) {
            if ($entry->name === $name) {
                return $entry;
            }
        }

        return null;
    }

    public function first(): ?ArchiveEntry
    {
        return $this->list()[0] ?? null;
    }

    public function find(callable $fn): ?ArchiveEntry
    {
        foreach ($this->list() as $entry) {
            if ($fn($entry)) {
                return $entry;
            }
        }

        return null;
    }

    public function each(callable $fn): void
    {
        foreach ($this->list() as $entry) {
            $fn($entry);
        }
    }

    public function extractAll(string $path): void
    {
        if ($this->format === 'zip') {
            $zip = $this->zip();
            $zip->extractTo($path);
            return;
        }

        $phar = $this->phar();
        $phar->extractTo($path, null, true);
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getZip(): ZipArchive
    {
        return $this->zip();
    }

    public function read(string $name): string
    {
        if ($this->format === 'zip') {
            $data = $this->zip()->getFromName($name);
            if ($data === false) {
                throw new ConfigurationException(new Message('extensions.failed_to_read_the_archive_entry'));
            }

            return $data;
        }

        $phar = $this->phar();
        if (!isset($phar[$name])) {
            throw new ConfigurationException(new Message('extensions.failed_to_read_the_archive_entry'));
        }

        /** @var PharFileInfo $file */
        $file = $phar[$name];
        return $file->getContent();
    }

    public function stream(string $name): mixed
    {
        if ($this->format === 'zip') {
            $resource = $this->zip()->getStream($name);
            if ($resource === false) {
                throw new ConfigurationException(new Message('extensions.failed_to_open_the_archive_stream'));
            }
            return $resource;
        }

        $path = $this->pharPath();
        $resource = fopen('phar://' . $path . '/' . ltrim($name, '/'), 'rb');
        if ($resource === false) {
            throw new ConfigurationException(new Message('extensions.failed_to_open_the_archive_stream'));
        }

        return $resource;
    }

    private function zip(): ZipArchive
    {
        if ($this->format !== 'zip') {
            throw new ConfigurationException(new Message('extensions.only_zip_archives_are_supported'));
        }

        if (!extension_loaded('zip')) {
            throw new ConfigurationException(new Message('extensions.the_zip_extension_is_not_installed'));
        }

        if ($this->zip instanceof ZipArchive) {
            return $this->zip;
        }

        $temp = $this->tempManager()->createFromContent($this->content);
        try {
            $zip = new ZipArchive();
            if ($zip->open($temp) !== true) {
                throw new ConfigurationException(new Message('extensions.failed_to_open_the_zip_archive'));
            }
        } catch (Throwable $exception) {
            $this->tempManager()->cleanupOrFail($temp);
            if ($exception instanceof ConfigurationException) {
                throw $exception;
            }
            throw new ConfigurationException(new Message('extensions.failed_to_open_the_zip_archive'), 0, $exception);
        }

        $this->tempPath = $temp;
        $this->zip = $zip;

        return $zip;
    }

    private function phar(): PharData
    {
        if ($this->format !== 'tar' && $this->format !== 'tar.gz') {
            throw new ConfigurationException(new Message('extensions.only_tar_archives_are_supported'));
        }

        if (!extension_loaded('phar')) {
            throw new ConfigurationException(new Message('extensions.the_phar_extension_is_not_installed'));
        }

        if ($this->phar instanceof PharData) {
            return $this->phar;
        }

        $suffix = $this->format === 'tar.gz' ? '.tar.gz' : '.tar';
        $temp = $this->tempManager()->createFromContent($this->content, $suffix);
        try {
            $phar = new PharData($temp);
        } catch (Throwable $exception) {
            $this->tempManager()->cleanupOrFail($temp);
            throw new ConfigurationException(new Message('extensions.failed_to_open_the_tar_archive'), 0, $exception);
        }

        $this->tempPath = $temp;
        $this->phar = $phar;

        return $this->phar;
    }

    /**
     * @return array<ArchiveEntry>
     */
    private function listZip(): array
    {
        $zip = $this->zip();
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $entries[] = new ArchiveEntry(
                archive: $this,
                name: (string) ($stat['name'] ?? ''),
                size: (int) ($stat['size'] ?? 0),
                compressedSize: (int) ($stat['comp_size'] ?? 0),
                isDirectory: str_ends_with((string) ($stat['name'] ?? ''), '/'),
                modifiedAt: new DateTimeImmutable('@' . (int) ($stat['mtime'] ?? time())),
            );
        }

        return $entries;
    }

    /**
     * @return array<ArchiveEntry>
     */
    private function listPhar(): array
    {
        $phar = $this->phar();
        $entries = [];

        $iterator = new RecursiveIteratorIterator($phar);
        foreach ($iterator as $file) {
            if (!$file instanceof PharFileInfo) {
                continue;
            }

            $name = method_exists($file, 'getRelativePathname')
                ? $file->getRelativePathname()
                : $file->getFilename();

            $entries[] = new ArchiveEntry(
                archive: $this,
                name: (string) $name,
                size: (int) $file->getSize(),
                compressedSize: method_exists($file, 'getCompressedSize')
                    ? (int) $file->getCompressedSize()
                    : (int) $file->getSize(),
                isDirectory: $file->isDir(),
                modifiedAt: new DateTimeImmutable('@' . (int) $file->getMTime()),
            );
        }

        return $entries;
    }

    private function tempManager(): ArchiveTempFileManager
    {
        if ($this->tempManager instanceof ArchiveTempFileManager) {
            return $this->tempManager;
        }

        $this->tempManager = new ArchiveTempFileManager($this->config, $this->tempProvider);
        return $this->tempManager;
    }

    private function pharPath(): string
    {
        if ($this->tempPath === null) {
            $this->phar();
        }

        if ($this->tempPath === null) {
            throw new ConfigurationException(new Message('extensions.failed_to_prepare_the_archive'));
        }

        return $this->tempPath;
    }

    public function __destruct()
    {
        if ($this->zip instanceof ZipArchive) {
            $this->zip->close();
        }

        if ($this->tempPath !== null) {
            $manager = $this->tempManager;
            if (!$manager instanceof ArchiveTempFileManager) {
                try {
                    $manager = new ArchiveTempFileManager($this->config, $this->tempProvider);
                } catch (Throwable) {
                    return;
                }
            }

            $manager->cleanupQuietly($this->tempPath);
        }
    }
}
