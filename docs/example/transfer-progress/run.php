<?php

declare(strict_types=1);

namespace Example\TransferProgress;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Files\FileInput;
use Example\Files\FilesClient;
use Example\Files\Resources\Files\BinaryUploadRequest;
use Example\Files\Resources\Files\DownloadFileRequest;
use RuntimeException;

require __DIR__ . '/../sdk/bootstrap.php';

$server = new LocalServer();
try {
    $client = new FilesClient(new ClientConfig(baseUrl: $server->url), HttpTransport::createDefault());
    $receive = new LatestProgress();
    $options = RequestOptions::empty()->withTransferProgress($receive);
    $input = FileInput::fromContent(str_repeat('Z', 262144), 'fixture.bin');
    $upload = $client->send((new BinaryUploadRequest($input))
        ->withOptions($options->withUrl($server->url . '/upload')))->dataOrFail();
    $latest = $receive->take();
    if ($upload['received'] !== 262144 || $latest->uploaded !== 262144) {
        throw new RuntimeException('Прогресс upload не совпал с переданными данными');
    }
    $summary = ['uploadBytes' => $latest->uploaded];
    $download = $client->sendAsync((new DownloadFileRequest(1))->withOptions(
        $options->withUrl($server->url . '/retry')->withRetry(2)->withRetryDelay(baseDelay: 0, jitter: false),
    ))->wait()->dataOrFail();
    $latest = $receive->take();
    if ($latest->attempt !== 2 || $download->size() !== 262144) {
        throw new RuntimeException('Повтор должен иметь собственный номер и счётчик');
    }
    $summary['retryAttempt'] = $latest->attempt;
    $summary['downloadBytes'] = $latest->downloaded;
    $download->close();
    $unknown = $client->send((new DownloadFileRequest(1))
        ->withOptions($options->withUrl($server->url . '/unknown')))->dataOrFail();
    $latest = $receive->take();
    if ($latest->downloadTotal !== null || $unknown->size() !== 262144) {
        throw new RuntimeException('Неизвестный total должен оставаться null');
    }
    $summary['unknownTotal'] = $latest->downloadTotal;
    $unknown->close();
    // Эти значения — счётчики HTTP-библиотеки, не подтверждение обработки данных сервером.
    echo json_encode($summary, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
    return $summary;
} finally {
    $server->close();
}
