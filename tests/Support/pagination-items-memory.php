<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Tests\Stubs\Pagination\NonRecordingPageTransport;
use ApiSutra\Tests\Stubs\Pagination\PageRequest;
use ApiSutra\Tests\Stubs\TestClient;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

// Свежий процесс без recording, накопительного logger и сохранения items приложением.
$pages = (int) ($argv[1] ?? 1000);
$transport = new NonRecordingPageTransport($pages);
$client = new TestClient(new ClientConfig(baseUrl: 'https://memory.test', paginationConfig: new PaginationConfig(maxPages: null)), $transport);
$count = 0;
foreach (new PageRequest()->setClient($client)->paginate()->items() as $item) {
    ++$count;
}
if ($count !== $pages || $transport->sent !== $pages) {
    throw new LogicException('Неполный обход');
}
echo json_encode(['pages' => $count, 'peakBytes' => memory_get_peak_usage(true)], JSON_THROW_ON_ERROR) . PHP_EOL;
