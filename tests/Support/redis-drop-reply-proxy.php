<?php

declare(strict_types=1);

// Узкий стенд: потерять подтверждение выбранной команды после её исполнения.
/** @param resource $stream */
function frame($stream): string
{
    $line = fgets($stream);
    if ($line === false) { throw new RuntimeException('Нет RESP frame'); }
    $result = $line;
    if ($line[0] === '*') {
        for ($i = 0; $i < (int) substr($line, 1); $i++) { $result .= frame($stream); }
    } elseif ($line[0] === '$' && (int) substr($line, 1) >= 0) {
        $remaining = (int) substr($line, 1) + 2;
        while ($remaining > 0) {
            $part = fread($stream, $remaining);
            if ($part === false || $part === '') { throw new RuntimeException('Неполный RESP frame'); }
            $result .= $part;
            $remaining -= strlen($part);
        }
    }
    return $result;
}
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) { throw new RuntimeException('Не создан тестовый proxy'); }
echo stream_socket_get_name($server, false) . "\n";
flush();
$client = stream_socket_accept($server, 10);
$upstream = stream_socket_client('tcp://' . $argv[1] . ':' . $argv[2], $errno, $error, 2);
if ($client === false || $upstream === false) { throw new RuntimeException('Нет соединения стенда'); }
stream_set_timeout($client, 3);
stream_set_timeout($upstream, 3);
$dropAt = (int) ($argv[3] ?? 1);
for ($forwarded = 1; $forwarded <= $dropAt; $forwarded++) {
    $command = frame($client);
    fwrite($upstream, $command);
    $reply = frame($upstream);
    if ($forwarded < $dropAt) { fwrite($client, $reply); }
}
fclose($client);
fclose($upstream);
fclose($server);
echo json_encode(['forwarded' => $dropAt, 'reply' => $reply], JSON_THROW_ON_ERROR) . "\n";
