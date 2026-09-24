<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use RuntimeException;

final class LocalFileServer
{
    /** @var resource|null */
    private mixed $process;
    /** @var array<int, resource> */
    private array $pipes = [];
    public readonly string $url;

    public function __construct()
    {
        $this->process = proc_open([PHP_BINARY, '-d', 'memory_limit=64M', __DIR__ . '/file-http-server.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($this->process)) {
            throw new RuntimeException('Не удалось запустить файловый HTTP-стенд');
        }
        $this->pipes = $pipes;
        stream_set_timeout($pipes[1], 5);
        $url = fgets($pipes[1]);
        if ($url === false) {
            throw new RuntimeException('HTTP-стенд не сообщил адрес');
        }
        $this->url = trim($url);
    }

    public function close(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            foreach ($this->pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($this->process);
            $this->process = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
