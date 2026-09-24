<?php

declare(strict_types=1);

namespace ApiSutra\Extensions\Archive\Temp;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

final readonly class NativeTempDirectoryProvider implements TempDirectoryProviderInterface
{
    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        $baseDir = $baseDir ?? '';
        $this->baseDir = $baseDir === '' ? sys_get_temp_dir() : $baseDir;
    }

    public function createTempFile(?string $suffix = null): string
    {
        $this->assertWritableDir($this->baseDir);

        $temp = tempnam($this->baseDir, 'archive_');
        if ($temp === false) {
            throw new ConfigurationException(new Message('extensions.failed_to_create_the_temporary_archive_file'));
        }

        if ($suffix === null || $suffix === '') {
            return $temp;
        }

        $withSuffix = $temp . $suffix;
        if (!rename($temp, $withSuffix)) {
            $this->cleanupQuietly($temp);
            throw new ConfigurationException(new Message('extensions.failed_to_prepare_the_temporary_archive_file'));
        }

        return $withSuffix;
    }

    public function cleanup(string $path): void
    {
        if ($path === '') {
            return;
        }

        if (!file_exists($path)) {
            return;
        }

        if (!@unlink($path)) {
            throw new ConfigurationException(new Message('extensions.failed_to_remove_the_temporary_archive_file'));
        }
    }

    private function assertWritableDir(string $path): void
    {
        if (!is_dir($path) || !is_writable($path)) {
            throw new ConfigurationException(new Message('extensions.temporary_directory_is_not_writable'));
        }
    }

    private function cleanupQuietly(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }

        @unlink($path);
    }
}
