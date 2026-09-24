<?php

declare(strict_types=1);

namespace ApiSutra\Files;

use ApiSutra\Exceptions\Files\FileTransferException;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Throwable;

/** Адаптер использует поток, но не владеет исходной ручкой. */
final class BorrowedStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private StreamInterface $stream;

    public function close(): void
    {
    }

    public function detach(): mixed
    {
        return null;
    }

    public function read(mixed $length): string
    {
        try {
            $data = $this->stream->read($length);
            if ($length > 0 && $data === '' && !$this->stream->eof()) {
                throw new FileTransferException('read_no_progress');
            }
            return $data;
        } catch (Throwable $exception) {
            throw new FileTransferException('read', previous: $exception);
        }
    }

    public function getContents(): string
    {
        return Utils::copyToString($this);
    }
}
