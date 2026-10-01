<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support\TransferProgress;

use ApiSutra\VO\Http\TransferProgress;
use ApiSutra\Tests\Stubs\TransferProgress\GateDownloadRequest;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Tests\Stubs\Requests\BinaryUploadRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\LocalFileServer;
use ApiSutra\VO\Files\FileInput;
use Revolt\EventLoop;
use GuzzleHttp\Psr7\Utils;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once __DIR__ . '/common.php';

$size = (int) (getenv('APISUTRA_GATE_BYTES') ?: 16 * 1024 * 1024);
check($size > 0, 'положительный размер benchmark');
$path = tempnam(sys_get_temp_dir(), 'apisutra-gate-upload-');
$file = fopen($path, 'wb');
$chunk = str_repeat('Z', 65536);
for ($remaining = $size; $remaining > 0; $remaining -= 65536) {
    fwrite($file, substr($chunk, 0, min(65536, $remaining)));
}
fclose($file);
try {
    foreach (['upload', 'download'] as $direction) {
        foreach ([['sync', 1], ['async', 1], ['async', 4]] as [$mode, $concurrency]) {
            $server = new LocalFileServer();
            $callbackCount = 0;
            $latest = null;
            $callback = static function (TransferProgress $p) use (&$callbackCount, &$latest): void {
                ++$callbackCount;
                $latest = $p;
            };
            $native = static function () use (&$callbackCount): void {
                ++$callbackCount;
            };
            $clients = [
                'off' => new TestClient(new ClientConfig(baseUrl: $server->url), transport()),
                'native' => new TestClient(new ClientConfig(baseUrl: $server->url), transport(['progress' => $native])),
                'snapshot' => new TestClient(new ClientConfig(baseUrl: $server->url), transport()),
            ];
            $measurements = [];
            for ($run = 0; $run < 7; ++$run) {
                // Чередуем порядок: первый прогон каждого режима — прогрев, затем шесть серий.
                $order = ['off', 'native', 'snapshot'];
                for ($rotate = 0; $rotate < $run % 3; ++$rotate) {
                    $order[] = array_shift($order);
                }
                foreach ($order as $observation) {
                    $callbackCount = 0;
                    $latest = null;
                    gc_collect_cycles();
                    memory_reset_peak_usage();
                    $before = memory_get_usage();
                    $start = hrtime(true);
                    $handles = [];
                    $inputs = [];
                    for ($i = 0; $i < $concurrency; ++$i) {
                        if ($direction === 'upload') {
                            $inputs[] = $stream = Utils::streamFor(fopen($path, 'rb'));
                            $input = FileInput::fromStream($stream, 'fixture.bin');
                            $request = new BinaryUploadRequest($input);
                        } else {
                            $request = new GateDownloadRequest('/download?size=' . $size);
                        }
                        $request->setClient($clients[$observation]);
                        if ($observation === 'snapshot') {
                            $request = observe($request, $callback);
                        }
                        $handles[] = $mode === 'async' ? $request->sendAsync() : $request->send();
                    }
                    foreach ($handles as $handle) {
                        $result = ($mode === 'async' ? $handle->wait() : $handle)->raw();
                        check($result->isSuccess(), 'передача benchmark');
                        if ($direction === 'download') {
                            check($result->data->size() === $size, 'размер benchmark');
                            $result->data->close();
                        } else {
                            check($result->data['bytes'] === $size, 'размер upload benchmark');
                        }
                    }
                    $elapsed = (hrtime(true) - $start) / 1e6;
                    $peak = memory_get_peak_usage() - $before;
                    foreach ($inputs as $input) {
                        $input->close();
                    }
                    unset($handles, $handle, $result, $request, $inputs, $input, $stream);
                    gc_collect_cycles();
                    check(EventLoop::getIdentifiers() === [], 'benchmark loop пуст');
                    if ($run > 0) {
                        $measurements[$observation][] = [$elapsed, $peak, $callbackCount, memory_get_usage() - $before];
                    }
                }
            }
            foreach ($measurements as $observation => $series) {
                $times = array_column($series, 0);
                sort($times);
                report('network', ['direction' => $direction, 'mode' => $mode, 'concurrency' => $concurrency,
                    'observation' => $observation, 'bytesPerTransfer' => $size, 'runs' => count($series),
                    'medianMs' => ($times[2] + $times[3]) / 2, 'minMs' => $times[0], 'maxMs' => $times[5],
                    'maxPeakBytes' => max(array_column($series, 1)), 'events' => array_column($series, 2),
                    'maxRetainedBytes' => max(array_column($series, 3))]);
            }
            $server->close();
        }
    }
} finally {
    unlink($path);
}
