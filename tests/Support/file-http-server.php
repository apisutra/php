<?php

declare(strict_types=1);

// Локальный HTTP-стенд читает и пишет порциями, в том числе при memory_limit=64M.
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) {
    throw new RuntimeException('Не удалось открыть локальный сокет');
}
echo 'http://' . stream_socket_get_name($server, false) . "\n";
flush();
$counts = [];
while (($socket = stream_socket_accept($server, -1)) !== false) {
    stream_set_timeout($socket, 10);
    $line = (string) fgets($socket);
    $target = explode(' ', $line)[1] ?? '/';
    $path = parse_url($target, PHP_URL_PATH);
    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
    $headers = [];
    while (($line = fgets($socket)) !== false && trim($line) !== '') {
        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower($name)] = trim($value);
    }
    if (strtolower($headers['expect'] ?? '') === '100-continue') {
        fwrite($socket, "HTTP/1.1 100 Continue\r\n\r\n");
    }
    $hash = hash_init('sha256');
    $bytes = 0;
    $chunked = strtolower($headers['transfer-encoding'] ?? '') === 'chunked';
    $remaining = (int) ($headers['content-length'] ?? 0);
    while (true) {
        if ($chunked) {
            $remaining = hexdec(trim((string) fgets($socket)));
            if ($remaining === 0) {
                fgets($socket);
                break;
            }
        }
        while ($remaining > 0) {
            $part = fread($socket, min(65536, $remaining));
            if ($part === false || $part === '') {
                break 2;
            }
            $bytes += strlen($part);
            $remaining -= strlen($part);
            hash_update($hash, $part);
        }
        if (!$chunked) {
            break;
        }
        fgets($socket);
    }
    $counts[$path] = ($counts[$path] ?? 0) + 1;
    $status = str_starts_with((string) $path, '/retry-') && $counts[$path] === 1 ? 503 : 200;
    $size = (int) ($query['size'] ?? 64);
    if (str_contains((string) $path, 'upload')) {
        $body = json_encode(['bytes' => $bytes, 'sha256' => hash_final($hash), 'attempt' => $counts[$path], 'target' => $target], JSON_THROW_ON_ERROR);
        fwrite($socket, "HTTP/1.1 $status Test\r\nContent-Type: application/json\r\nConnection: close\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
    } elseif ($path === '/gzip') {
        // Ограниченная фикстура проверяет размер после декомпрессии.
        $body = gzencode(str_repeat('Z', min($size, 262144)));
        fwrite($socket, "HTTP/1.1 200 OK\r\nContent-Type: application/octet-stream\r\nContent-Encoding: gzip\r\nConnection: close\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
    } else {
        if ($path === '/error') {
            $status = 500;
        }
        $size = $status === 503 ? 3 : $size;
        $unknown = $path === '/unknown';
        fwrite($socket, "HTTP/1.1 $status Test\r\nContent-Type: application/octet-stream\r\nConnection: close\r\n" . ($unknown ? '' : "Content-Length: $size\r\n") . "\r\n");
        $remaining = $path === '/truncated' ? min(3, $size) : $size;
        $chunk = str_repeat('Z', 65536);
        while ($remaining > 0) {
            $sent = @fwrite($socket, substr($chunk, 0, min(65536, $remaining)));
            if ($sent === false || $sent === 0) {
                break;
            }
            $remaining -= $sent;
        }
    }
    fclose($socket);
}
