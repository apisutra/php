<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use RuntimeException;

final class LocalTimeoutServer
{
    /** @var resource */
    private mixed $process;
    /** @var array<int, resource> */
    private array $pipes;
    public readonly string $httpUrl;
    public readonly string $tlsUrl;

    public function __construct()
    {
        $this->process = proc_open([PHP_BINARY, __DIR__ . '/timeout-http-server.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($this->process)) {
            throw new RuntimeException('Не удалось запустить локальный HTTP-стенд');
        }
        $this->pipes = $pipes;
        stream_set_timeout($pipes[1], 5);
        $line = fgets($pipes[1]);
        if ($line === false) {
            $this->close();
            throw new RuntimeException('Локальный HTTP-стенд не сообщил адреса');
        }
        $addresses = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        $this->httpUrl = 'http://' . $addresses['http'];
        $this->tlsUrl = 'https://' . $addresses['tls'];
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
