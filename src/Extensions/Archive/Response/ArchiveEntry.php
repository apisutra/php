<?php

declare(strict_types=1);

namespace ApiSutra\Extensions\Archive\Response;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use DateTimeInterface;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

final readonly class ArchiveEntry
{
    public function __construct(
        private ArchiveResponse $archive,
        public string $name,
        public int $size,
        public int $compressedSize,
        public bool $isDirectory,
        public DateTimeInterface $modifiedAt,
    ) {
    }

    public function contents(): string
    {
        return $this->archive->read($this->name);
    }

    public function stream(): StreamInterface
    {
        $resource = $this->archive->stream($this->name);
        return Utils::streamFor($resource);
    }

    public function saveTo(string $path): void
    {
        $stream = $this->stream();
        $resource = fopen($path, 'wb');
        if ($resource === false) {
            throw new ConfigurationException(new Message('extensions.failed_to_save_the_file_to_disk'));
        }

        while (!$stream->eof()) {
            fwrite($resource, $stream->read(8192));
        }

        fclose($resource);
    }
}
