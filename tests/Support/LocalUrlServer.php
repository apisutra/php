<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use RuntimeException;

final class LocalUrlServer
{
    /** @var resource */
    private mixed $process;
    /** @var array<int, resource> */
    private array $pipes;
    public readonly string $url;
    public readonly string $otherUrl;

    public function __construct()
    {
        $this->process = proc_open([PHP_BINARY, __DIR__ . '/url-http-server.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($this->process)) {
            throw new RuntimeException('Не удалось запустить HTTP-стенд');
        }
        $this->pipes = $pipes;
        stream_set_timeout($pipes[1], 5);
        $line = fgets($pipes[1]);
        if ($line === false) {
            $this->close();
            throw new RuntimeException('HTTP-стенд не сообщил адреса');
        }
        $addresses = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        $this->url = 'http://' . $addresses[0];
        $this->otherUrl = 'http://' . $addresses[1];
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
