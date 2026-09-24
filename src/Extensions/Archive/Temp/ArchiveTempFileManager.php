<?php

declare(strict_types=1);

namespace ApiSutra\Extensions\Archive\Temp;

use ApiSutra\Localization\Message;
use ApiSutra\Config\ArchiveConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use Throwable;

final readonly class ArchiveTempFileManager
{
    private TempDirectoryProviderInterface $provider;
    private ?int $maxSize;

    public function __construct(?ClientConfig $config = null, ?TempDirectoryProviderInterface $provider = null)
    {
        $archiveConfig = $config ? ArchiveConfig::fromClientConfig($config) : null;
        $resolver = new ArchiveTempDriverResolver();
        $this->provider = $resolver->resolve($archiveConfig, $provider ?? $archiveConfig?->tempProvider);
        $this->maxSize = $archiveConfig?->maxSize;
    }

    public function createFromContent(string $content, ?string $suffix = null): string
    {
        $this->assertMaxSize($content);

        $path = $this->provider->createTempFile($suffix);
        try {
            $written = file_put_contents($path, $content);
            if ($written === false || $written !== strlen($content)) {
                throw new ConfigurationException(new Message('extensions.failed_to_write_the_archive_to_a_temporary_file'));
            }
        } catch (Throwable $exception) {
            $this->cleanupOrFail($path);
            if ($exception instanceof ConfigurationException) {
                throw $exception;
            }

            throw new ConfigurationException(new Message('extensions.failed_to_prepare_the_temporary_archive_file'), 0, $exception);
        }

        return $path;
    }

    public function cleanupOrFail(string $path): void
    {
        $this->provider->cleanup($path);
    }

    public function cleanupQuietly(string $path): void
    {
        try {
            $this->provider->cleanup($path);
        } catch (Throwable) {
        }
    }

    private function assertMaxSize(string $content): void
    {
        if ($this->maxSize === null || $this->maxSize <= 0) {
            return;
        }

        if (strlen($content) <= $this->maxSize) {
            return;
        }

        throw new ConfigurationException(new Message('extensions.archive_size_exceeds_the_allowed_limit'));
    }
}
