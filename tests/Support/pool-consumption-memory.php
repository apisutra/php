<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Tests\Stubs\Async\ProbeRequest;
use ApiSutra\Tests\Stubs\Execution\Pool\NonRecordingTransport;
use ApiSutra\Tests\Stubs\TestClient;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

// Свежий процесс, обычный GC, никаких накопителей запросов или результатов в стенде.
$count = (int) ($argv[1] ?? 1000);
$failed = ($argv[2] ?? 'success') === 'failed';
$throw = ($argv[3] ?? '') === 'throw';
$client = new TestClient(new ClientConfig(baseUrl: 'https://memory.test', throwOnErrors: $throw, retry: new RetryConfig(attempts: 1)), new NonRecordingTransport($failed));
$source = static function () use ($count): Generator {
    for ($i = 0; $i < $count; $i++) {
        yield new ProbeRequest();
    }
};
$processed = 0;
$start = hrtime(true);
try {
    $summary = $client->pool($source(), 8)->withResponseHandler(static function () use (&$processed): void {
        $processed++;
    })->consume();
    if ($summary->total !== $count) {
        throw new LogicException('Неполный обход');
    }
} catch (SdkException $error) {
    if (!$failed || !$throw || $processed !== $count) {
        throw $error;
    }
}
echo json_encode([
    'count' => $processed,
    'failed' => $failed,
    'throw' => $throw,
    'peakBytes' => memory_get_peak_usage(true),
    'elapsedMs' => (hrtime(true) - $start) / 1e6,
], JSON_THROW_ON_ERROR) . PHP_EOL;
