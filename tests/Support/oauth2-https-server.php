<?php

declare(strict_types=1);

// Изолированный одноразовый TLS endpoint: fixture secrets никогда не уходят во внешнюю сеть.
[$script, $certificate, $capture, $status] = $argv;
$context = stream_context_create(['ssl' => ['local_cert' => $certificate, 'verify_peer' => false]]);
$server = stream_socket_server('tls://127.0.0.1:0', $error, $message, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    throw new RuntimeException($message);
}
echo stream_socket_get_name($server, false) . "\n";
flush();
$socket = stream_socket_accept($server, 10);
if ($socket === false) {
    exit(2);
}
stream_set_timeout($socket, 5);
$head = '';
while (($line = fgets($socket)) !== false && $line !== "\r\n") {
    $head .= $line;
}
preg_match('/Content-Length:\s*(\d+)/i', $head, $match);
$length = (int) ($match[1] ?? 0);
$body = '';
while (strlen($body) < $length && !feof($socket)) {
    $body .= fread($socket, $length - strlen($body));
}
file_put_contents($capture, json_encode(['headers' => $head, 'body' => $body], JSON_THROW_ON_ERROR));
$payload = '{"access_token":"fixture-token","token_type":"Bearer"}';
$location = $status === '302' ? "Location: /redirected\r\n" : '';
fwrite($socket, "HTTP/1.1 $status Response\r\nContent-Type: application/json\r\n{$location}Content-Length: " . strlen($payload) . "\r\nConnection: close\r\n\r\n" . $payload);
fclose($socket);
fclose($server);
