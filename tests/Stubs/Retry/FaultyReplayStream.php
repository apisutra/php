<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Retry;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class FaultyReplayStream implements StreamInterface
{
    use StreamDecoratorTrait;

    public function __construct(private StreamInterface $stream, private string $failure) {}

    public function tell(): int
    {
        if ($this->failure === 'tell') {
            throw new RuntimeException('fixture tell failure');
        }
        return $this->stream->tell();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($this->failure === 'seek') {
            throw new RuntimeException('fixture seek failure');
        }
        if ($this->failure !== 'silent') {
            $this->stream->seek($offset, $whence);
        }
    }
}
