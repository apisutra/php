<?php

declare(strict_types=1);

namespace ApiSutra\Transport;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;
use Throwable;

/** @internal Переносит ошибку потока за границу native cURL, сохраняя соседние передачи. */
final class CallbackStream implements StreamInterface
{
    use StreamDecoratorTrait;

    public private(set) ?Throwable $failure = null;

    public function __construct(private StreamInterface $stream)
    {
    }

    public function read(mixed $length): string
    {
        try {
            return $this->stream->read($length);
        } catch (Throwable $error) {
            $this->failure ??= $error;
            return '';
        }
    }

    public function write(mixed $string): int
    {
        try {
            return $this->stream->write($string);
        } catch (Throwable $error) {
            $this->failure ??= $error;
            return 0;
        }
    }

    public function close(): void
    {
    }

    public function detach(): mixed
    {
        return null;
    }
}
