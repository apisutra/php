<?php

declare(strict_types=1);

// Запуск: php -d memory_limit=64M tests/Support/streaming-memory-smoke.php binary|multipart|download|path|sink
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use ApiSutra\Tests\Stubs\Requests\BinaryUploadRequest;
use ApiSutra\Tests\Stubs\Requests\MultipartUploadRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Tests\Support\LocalFileServer;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\Transport\RecordingTransport;
use ApiSutra\VO\Files\FileInput;
use GuzzleHttp\Psr7\Utils;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$mode = $argv[1] ?? 'download';
$size = 128 * 1024 * 1024;
$directory = sys_get_temp_dir() . '/apisutra-memory-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
$server = new LocalFileServer();
$transport = new RecordingTransport(HttpTransport::createDefault(), $directory . '/recordings');
$client = new TestClient(new ClientConfig(baseUrl: $server->url, cacheConfig: new CacheConfig(store: new ArrayCache()), debug: true), $transport);
$file = null;
$sink = null;
$result = null;
try {
    $hash = hash_init('sha256');
    $chunk = str_repeat('Z', 65536);
    for ($offset = 0; $offset < $size; $offset += strlen($chunk)) {
        hash_update($hash, $chunk);
    }
    $expected = hash_final($hash);
    if (in_array($mode, ['binary', 'multipart'], true)) {
        $resource = fopen($directory . '/source', 'wb');
        for ($offset = 0; $offset < $size; $offset += strlen($chunk)) {
            fwrite($resource, $chunk);
        }
        fclose($resource);
        $file = FileInput::fromPath($directory . '/source');
        $request = $mode === 'binary' ? new BinaryUploadRequest($file) : new MultipartUploadRequest([$file], 'fixture');
        $result = $request->setClient($client)->withUrl($server->url . '/upload')->send()->raw();
        if (!$result->isSuccess()) {
            throw $result->exception ?? new RuntimeException('Ошибка upload');
        }
        if ($mode === 'binary' && ($result->data['bytes'] !== $size || $result->data['sha256'] !== $expected)) {
            throw new RuntimeException('Неверные байты binary upload');
        }
        if ($mode === 'multipart') {
            $body = $result->debug->preparedRequest->stream;
            $body->rewind();
            $hash = hash_init('sha256');
            $count = 0;
            while (!$body->eof()) {
                $chunk = $body->read(65536);
                $count += strlen($chunk);
                hash_update($hash, $chunk);
            }
            if ($count <= $size || $count > $size + 4096 || $result->data['bytes'] !== $count || $result->data['sha256'] !== hash_final($hash)) {
                throw new RuntimeException('Неверные байты multipart upload');
            }
        }
    } else {
        $execution = (new ProviderBDownloadRequest('one'))->setClient($client)->withUrl($server->url . '/download?size=' . $size);
        if ($mode === 'path') {
            $execution = $execution->withDownloadTo($directory . '/destination');
        } elseif ($mode === 'sink') {
            $sink = Utils::streamFor(fopen($directory . '/destination', 'w+b'));
            $execution = $execution->withDownloadTo($sink);
        }
        $result = $execution->send()->raw();
        if (!$result->isSuccess()) {
            throw $result->exception ?? new RuntimeException('Ошибка download');
        }
        $hash = hash_init('sha256');
        $stream = $result->data->stream();
        while (!$stream->eof()) {
            hash_update($hash, $stream->read(65536));
        }
        if ($result->data->size() !== $size || hash_final($hash) !== $expected) {
            throw new RuntimeException('Неверные байты download');
        }
        if ($mode !== 'download' && (filesize($directory . '/destination') !== $size || hash_file('sha256', $directory . '/destination') !== $expected)) {
            throw new RuntimeException('Неверные байты назначения download');
        }
        $result->data->close();
    }
    foreach (glob($directory . '/recordings/*') ?: [] as $record) {
        if (filesize($record) > 16384) {
            throw new RuntimeException('Recorder материализовал файл');
        }
    }
    echo json_encode(['mode' => $mode, 'bytes' => $size, 'peakBytes' => memory_get_peak_usage(true)], JSON_THROW_ON_ERROR) . "\n";
} finally {
    $file?->close();
    $sink?->close();
    $server->close();
    foreach (glob($directory . '/recordings/*') ?: [] as $record) {
        unlink($record);
    }
    @rmdir($directory . '/recordings');
    foreach (glob($directory . '/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($directory);
}
