<?php

declare(strict_types=1);

namespace Example\TransferProgress;

use RuntimeException;

/** Локальный стенд примера; не часть SDK. */
final class LocalServer
{
    /** @var resource */
    private mixed $process;
    /** @var array<int, resource> */
    private array $pipes;
    public readonly string $url;

    public function __construct()
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/../server.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Не удалось запустить локальный HTTP');
        }
        $this->process = $process;
        $this->pipes = $pipes;
        stream_set_timeout($pipes[1], 5);
        $url = fgets($pipes[1]);
        if ($url === false) {
            $this->close();
            throw new RuntimeException('Стенд не сообщил адрес');
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
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
