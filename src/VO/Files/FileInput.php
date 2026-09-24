<?php

declare(strict_types=1);

namespace ApiSutra\VO\Files;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

readonly class FileInput
{
    private function __construct(
        public StreamInterface $stream,
        public string $filename,
        public ?string $mimeType = null,
        public ?int $size = null,
        private ?StreamInterface $ownedStream = null,
    ) {
    }

    public static function fromPath(string $path): self
    {
        $resource = fopen($path, 'rb');
        if ($resource === false) {
            throw new ConfigurationException(new Message('vo.failed_to_open_file', ['path' => $path]));
        }

        return new self(
            stream: $stream = Utils::streamFor($resource),
            ownedStream: $stream,
            filename: basename($path),
            mimeType: mime_content_type($path) ?: null,
            size: ($size = filesize($path)) !== false ? $size : null,
        );
    }

    /**
     * Создаёт FileInput из пути без исключения при ошибке открытия.
     *
     * Возвращает null при любом сбое (файл не найден, нет прав и т.п.).
     * Удобно для path-flow: ошибку можно обработать через ValidationError.
     */
    public static function tryFromPath(string $path): ?self
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $resource = fopen($path, 'rb');
        if ($resource === false) {
            return null;
        }

        $mimeType = mime_content_type($path);
        $size = filesize($path);

        return new self(
            stream: $stream = Utils::streamFor($resource),
            ownedStream: $stream,
            filename: basename($path),
            mimeType: $mimeType ?: null,
            size: $size !== false ? $size : null,
        );
    }

    public static function fromContent(string $content, string $filename): self
    {
        return new self(
            stream: $stream = Utils::streamFor($content),
            ownedStream: $stream,
            filename: $filename,
            mimeType: null,
            size: strlen($content),
        );
    }

    public static function fromStream(StreamInterface $stream, string $filename): self
    {
        return new self(
            stream: $stream,
            filename: $filename,
            mimeType: null,
            size: null,
        );
    }

    /** Закрывает только собственную ручку; копии FileInput разделяют её. */
    public function close(): void
    {
        $this->ownedStream?->close();
    }

    public function withMimeType(string $mimeType): self
    {
        return new self(
            stream: $this->stream,
            ownedStream: $this->ownedStream,
            filename: $this->filename,
            mimeType: $mimeType,
            size: $this->size,
        );
    }

    /** Меняет имя без чтения потока; копия сохраняет владение той же ручкой. */
    public function withFilename(string $filename): self
    {
        return new self(
            stream: $this->stream,
            ownedStream: $this->ownedStream,
            filename: $filename,
            mimeType: $this->mimeType,
            size: $this->size,
        );
    }
}
