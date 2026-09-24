<?php

declare(strict_types=1);

namespace ApiSutra\Extensions\Archive\Temp;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Throwable;

final readonly class SpatieTempDirectoryProvider implements TempDirectoryProviderInterface
{
    private ?string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        if (!class_exists(TemporaryDirectory::class)) {
            throw new ConfigurationException(new Message('extensions.package_spatie_temporary_directory_is_not_installed'));
        }

        $this->baseDir = $baseDir;
    }

    public function createTempFile(?string $suffix = null): string
    {
        $baseDir = $this->resolveBaseDir();
        $directory = $baseDir !== null
            ? new TemporaryDirectory($baseDir)
            : new TemporaryDirectory();

        try {
            $directory->create();
        } catch (Throwable $exception) {
            throw new ConfigurationException(new Message('extensions.failed_to_create_the_temporary_archive_directory'), 0, $exception);
        }

        try {
            $token = bin2hex(random_bytes(8));
        } catch (Throwable $exception) {
            $this->removeDirectory($directory->path());
            throw new ConfigurationException(new Message('extensions.failed_to_prepare_the_temporary_archive_file'), 0, $exception);
        }

        $filename = 'archive_' . $token . ($suffix ?? '');

        return $directory->path($filename);
    }

    public function cleanup(string $path): void
    {
        if ($path === '') {
            return;
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            return;
        }

        $this->removeDirectory($directory);
    }

    private function resolveBaseDir(): ?string
    {
        if ($this->baseDir === null || $this->baseDir === '') {
            return null;
        }

        if (!is_dir($this->baseDir) || !is_writable($this->baseDir)) {
            throw new ConfigurationException(new Message('extensions.temporary_directory_is_not_writable'));
        }

        return $this->baseDir;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $target = $item->getPathname();
            if ($item->isDir()) {
                if (!@rmdir($target)) {
                    throw new ConfigurationException(new Message('extensions.failed_to_remove_the_temporary_archive_directory'));
                }
                continue;
            }

            if (!@unlink($target)) {
                throw new ConfigurationException(new Message('extensions.failed_to_remove_the_temporary_archive_directory'));
            }
        }

        if (!@rmdir($path)) {
            throw new ConfigurationException(new Message('extensions.failed_to_remove_the_temporary_archive_directory'));
        }
    }
}
