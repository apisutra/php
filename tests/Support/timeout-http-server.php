<?php

declare(strict_types=1);

// Локальный стенд: отложенный HTTP и TCP-порт без ответа на TLS handshake.
$http = stream_socket_server('tcp://127.0.0.1:0');
$tls = stream_socket_server('tcp://127.0.0.1:0');
if ($http === false || $tls === false) {
    exit(1);
}
stream_set_blocking($http, false);
stream_set_blocking($tls, false);
echo json_encode(['http' => stream_socket_get_name($http, false), 'tls' => stream_socket_get_name($tls, false)], JSON_THROW_ON_ERROR) . "\n";
flush();

/** @var array<int, array{socket: resource, tls: bool, buffer: string, due: ?float, response: string}> $clients */
$clients = [];
$peak = 0;
while (true) {
    $read = [$http, $tls];
    foreach ($clients as $client) {
        $read[] = $client['socket'];
    }
    $write = $except = null;
    if (stream_select($read, $write, $except, 0, 10000) === false) {
        break;
    }
    foreach ($read as $socket) {
        if ($socket === $http || $socket === $tls) {
            $connection = stream_socket_accept($socket, 0);
            if ($connection !== false) {
                stream_set_blocking($connection, false);
                $clients[(int) $connection] = ['socket' => $connection, 'tls' => $socket === $tls, 'buffer' => '', 'due' => null, 'response' => ''];
            }
            continue;
        }
        $id = (int) $socket;
        $chunk = fread($socket, 8192);
        if ($chunk === false || ($chunk === '' && feof($socket))) {
            fclose($socket);
            unset($clients[$id]);
            continue;
        }
        $clients[$id]['buffer'] .= $chunk;
        if ($clients[$id]['tls'] || $clients[$id]['due'] !== null || !str_contains($clients[$id]['buffer'], "\r\n\r\n")) {
            continue;
        }
        $target = explode(' ', $clients[$id]['buffer'])[1];
        parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
        $delay = (int) ($query['ms'] ?? 0);
        $status = (int) ($query['status'] ?? 200);
        $peak = max($peak, count(array_filter($clients, static fn (array $client): bool => !$client['tls'])));
        $body = json_encode(['ok' => true, 'delay' => $delay] + (isset($query['observe']) ? ['peak' => $peak] : []), JSON_THROW_ON_ERROR);
        if (parse_url($target, PHP_URL_PATH) === '/pages') {
            $page = (int) ($query['page'] ?? 1);
            $total = (int) ($query['total'] ?? 6);
            $delay = isset($query['ms']) ? $delay : ($page === 2 ? 120 : 10);
            if ($page === 1 && isset($query['ms'])) {
                $delay = 1;
                $status = 200;
            }
            $body = json_encode(['data' => [['page' => $page, 'peak' => $peak]], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => $total]], JSON_THROW_ON_ERROR);
        }
        $headers = "HTTP/1.1 {$status} Fixture\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n";
        if ($status === 302) {
            $headers .= "Location: /ok\r\n";
        }
        $headers .= "\r\n";
        if (($query['body'] ?? '') === 'slow') {
            fwrite($socket, $headers);
            $headers = '';
        }
        $clients[$id]['response'] = $headers . $body;
        $clients[$id]['due'] = microtime(true) + $delay / 1000;
    }
    foreach ($clients as $id => $client) {
        if ($client['due'] !== null && microtime(true) >= $client['due']) {
            @fwrite($client['socket'], $client['response']);
            fclose($client['socket']);
            unset($clients[$id]);
        }
    }
}
