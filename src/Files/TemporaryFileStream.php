<?php

declare(strict_types=1);

namespace ApiSutra\Files;

use ApiSutra\Exceptions\Files\FileTransferException;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Throwable;

/** Общий владелец временного файла; публикация снимает автоматическое удаление. */
final class TemporaryFileStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private StreamInterface $stream;

    private ?string $path = null;
    private bool $closed = false;

    public function __construct(?string $directory = null)
    {
        $directory ??= sys_get_temp_dir();
        $path = @tempnam($directory, '.apisutra-');
        if ($path === false || realpath(dirname($path)) !== realpath($directory)) {
            if (is_string($path)) {
                @unlink($path);
            }
            throw new FileTransferException('temporary_file');
        }
        $this->path = $path;
        $resource = @fopen($path, 'w+b');
        if ($resource === false) {
            @unlink($path);
            $this->path = null;
            throw new FileTransferException('temporary_file');
        }
        $this->stream = Utils::streamFor($resource);
    }

    public function write(mixed $string): int
    {
        try {
            $count = $this->stream->write($string);
            if ($string !== '' && $count === 0) {
                throw new FileTransferException('write_no_progress');
            }
            return $count;
        } catch (Throwable $exception) {
            throw new FileTransferException('write', previous: $exception);
        }
    }

    public function publish(string $target, bool $overwrite): void
    {
        DownloadManager::validatePath($target, $overwrite);
        if ($this->path === null || $this->closed || !is_file($this->path)) {
            throw new FileTransferException('publish_closed');
        }
        if ($overwrite) {
            if (!@rename($this->path, $target)) {
                throw new FileTransferException('publish');
            }
        } else {
            // Hard link атомарно создаёт имя и никогда не заменяет существующее.
            if (!@link($this->path, $target)) {
                throw new FileTransferException('publish');
            }
            @unlink($this->path);
        }
        $this->path = null;
    }

    public function close(): void
    {
        if (!$this->closed && isset($this->stream)) {
            $this->closed = true;
            $this->stream->close();
            if ($this->path !== null && file_exists($this->path)) {
                @unlink($this->path);
            }
            $this->path = null;
        }
    }

    public function detach(): mixed
    {
        // Выдача сырой ручки нарушила бы владение временным файлом.
        throw new FileTransferException('detach_not_supported');
    }

    public function __destruct()
    {
        $this->close();
    }
}
