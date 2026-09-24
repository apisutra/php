<?php

declare(strict_types=1);

// Локальный стенд: отражает точные target/headers/body и считает запросы на двух origin.
$servers = [];
foreach ([0, 1] as $index) {
    $servers[$index] = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($servers[$index] === false) {
        throw new RuntimeException('Не удалось открыть локальный сокет');
    }
}
$addresses = array_map(static fn ($socket): string => stream_socket_get_name($socket, false), $servers);
echo json_encode($addresses, JSON_THROW_ON_ERROR) . "\n";
flush();
$counts = [0, 0];
while (true) {
    $read = $servers;
    $write = $except = [];
    if (stream_select($read, $write, $except, 5) === false) {
        break;
    }
    foreach ($read as $index => $server) {
        $socket = stream_socket_accept($server, 1);
        if ($socket === false) {
            continue;
        }
        stream_set_timeout($socket, 3);
        $line = trim((string) fgets($socket));
        $target = explode(' ', $line)[1] ?? '';
        $headers = [];
        while (($line = fgets($socket)) !== false && trim($line) !== '') {
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower($name)] = trim($value);
        }
        if (strtolower($headers['expect'] ?? '') === '100-continue') {
            fwrite($socket, "HTTP/1.1 100 Continue\r\n\r\n");
        }
        $body = '';
        $length = (int) ($headers['content-length'] ?? 0);
        while (strlen($body) < $length) {
            $part = fread($socket, $length - strlen($body));
            if ($part === false || $part === '') {
                break;
            }
            $body .= $part;
        }
        $counts[$index]++;
        $status = preg_match('~^/redirect/(30[12378])~', $target, $match) ? (int) $match[1] : 200;
        $response = json_encode(['target' => $target, 'headers' => $headers, 'body' => $body, 'counts' => $counts], JSON_THROW_ON_ERROR);
        $location = $status !== 200 ? 'Location: http://' . $addresses[1] . "/leak\r\n" : '';
        fwrite($socket, "HTTP/1.1 $status Test\r\nContent-Type: application/json\r\nConnection: close\r\n" . $location . 'Content-Length: ' . strlen($response) . "\r\n\r\n" . $response);
        fclose($socket);
    }
}
