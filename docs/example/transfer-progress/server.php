<?php

declare(strict_types=1);

// Только loopback и искусственные данные; чтение/запись порциями, без внешнего API.
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) {
    throw new RuntimeException('Не удалось открыть локальный HTTP-сокет');
}
echo 'http://' . stream_socket_get_name($server, false) . "\n";
flush();
$attempt = 0;
while (($socket = stream_socket_accept($server, -1)) !== false) {
    stream_set_timeout($socket, 5);
    $line = explode(' ', (string) fgets($socket));
    $path = $line[1] ?? '/';
    $headers = [];
    while (($line = fgets($socket)) !== false && trim($line) !== '') {
        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower($name)] = trim($value);
    }
    if (strtolower($headers['expect'] ?? '') === '100-continue') {
        fwrite($socket, "HTTP/1.1 100 Continue\r\n\r\n");
    }
    $bytes = 0;
    $remaining = (int) ($headers['content-length'] ?? 0);
    while ($remaining > 0) {
        $part = fread($socket, min(65536, $remaining));
        if ($part === false || $part === '') {
            break;
        }
        $bytes += strlen($part);
        $remaining -= strlen($part);
    }
    if ($path === '/upload') {
        $body = json_encode(['received' => $bytes], JSON_THROW_ON_ERROR);
        fwrite($socket, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: "
            . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);
    } else {
        $status = $path === '/retry' && ++$attempt === 1 ? 503 : 200;
        $size = $status === 503 ? 3 : 262144;
        fwrite($socket, "HTTP/1.1 $status Test\r\nContent-Type: application/octet-stream\r\nConnection: close\r\n"
            . ($path === '/unknown' ? '' : "Content-Length: $size\r\n") . "\r\n");
        while ($size > 0) {
            $sent = fwrite($socket, str_repeat('Z', min(65536, $size)));
            if ($sent === false || $sent === 0) {
                break;
            }
            $size -= $sent;
        }
    }
    fclose($socket);
}
