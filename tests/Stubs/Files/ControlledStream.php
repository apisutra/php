<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Files;

use Closure;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class ControlledStream implements StreamInterface
{
    use StreamDecoratorTrait;

    public int $written = 0;
    public int $reads = 0;
    public bool $closed = false;

    public function __construct(
        private StreamInterface $stream,
        private int $maxWrite = 2,
        private ?int $failAfter = null,
        private bool $readFails = false,
        private ?Closure $onWrite = null,
    ) {}

    public function write(mixed $string): int
    {
        if ($this->failAfter !== null && $this->written >= $this->failAfter) {
            return 0;
        }
        $count = $this->stream->write(substr($string, 0, $this->maxWrite));
        $this->written += $count;
        ($this->onWrite)?->__invoke();
        return $count;
    }

    public function read(mixed $length): string
    {
        $this->reads++;
        if ($this->readFails) {
            throw new RuntimeException('Тестовая ошибка чтения');
        }
        return $this->stream->read($length);
    }

    public function close(): void
    {
        $this->closed = true;
        $this->stream->close();
    }
}
