<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Extensions\Archive\Temp\TempDirectoryProviderInterface;

final readonly class ArchiveConfig
{
    public function __construct(
        public string $driver = 'native',
        public ?string $tempDir = null,
        public ?int $maxSize = null,
        public ?TempDirectoryProviderInterface $tempProvider = null,
    ) {
    }

    public static function fromClientConfig(ClientConfig $config): self
    {
        return $config->archive instanceof self ? $config->archive : new self();
    }
}
