<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\VO\Files\FileResponse;
use Example\Files\FilesClient;
use Example\Files\Resources\Files\Base64UploadRequest;
use Example\Files\Resources\Files\BinaryUploadRequest;
use Example\Files\Resources\Files\DownloadFileRequest;
use Example\Files\Resources\Files\MultipartUploadRequest;
use GuzzleHttp\Psr7\Utils;

require __DIR__ . '/../sdk/bootstrap.php';

$fixture = __DIR__ . '/fixtures/report.txt';
$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake([
    MultipartUploadRequest::class => MockResponse::success(['id' => 7]),
    BinaryUploadRequest::class => MockResponse::success(['id' => 7]),
    Base64UploadRequest::class => MockResponse::success(['id' => 7]),
    DownloadFileRequest::class => MockResponse::file($fixture, headers: [
        'Content-Type' => 'text/plain',
        'Content-Disposition' => 'attachment; filename="report.txt"',
    ]),
]);
$client = new FilesClient(new ClientConfig(baseUrl: 'https://files.example.test'), $transport);

$uploads = [];
foreach (['multipart', 'binary', 'base64'] as $format) {
    // Каждый send получает новый источник, открытый с начала файла.
    $input = FileInput::fromPath($fixture)->withMimeType('text/plain');
    try {
        $request = match ($format) {
            'multipart' => new MultipartUploadRequest($input, 'Monthly report'),
            'binary' => new BinaryUploadRequest($input),
            'base64' => new Base64UploadRequest($input),
        };
        $result = $client->send($request)->dataOrFail();
        $recorded = $transport->getRecorded();
        $prepared = $recorded[array_key_last($recorded)];
        $contentType = $prepared->headers['Content-Type'];
        // Только для маленькой учебной фикстуры читаем поток целиком, чтобы показать байты запроса.
        $body = $prepared->body ?? (string) $prepared->stream;
        if ($format === 'multipart') {
            $boundary = substr($contentType, strlen('multipart/form-data; boundary='));
            $body = str_replace($boundary, 'EXAMPLE_BOUNDARY', $body);
            $contentType = 'multipart/form-data; boundary=EXAMPLE_BOUNDARY';
        }
        $uploads[$format] = [
            'id' => $result['id'],
            'contentType' => $contentType,
            'streamed' => $prepared->stream !== null && $prepared->body === null,
            'body' => $format === 'base64' ? json_decode($body, true, flags: JSON_THROW_ON_ERROR) : $body,
        ];
    } finally {
        $input->close();
    }
}

// Уникальный каталог не пересекается с файлами пользователя и удаляется после примера.
$directory = sys_get_temp_dir() . '/apisutra-files-example-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) {
    throw new RuntimeException('Не удалось создать каталог примера');
}
$sink = Utils::streamFor('');
$download = $streamDownload = $archiveFile = null;
try {
    /** @var FileResponse $download */
    $download = $client->send(
        (new DownloadFileRequest(7))->withDownloadTo($directory . '/report.txt'),
    )->dataOrFail();
    $downloads = [
        'filename' => $download->filename(),
        'mimeType' => $download->mimeType(),
        'size' => $download->size(),
        'savedContent' => file_get_contents($directory . '/report.txt'),
    ];

    /** @var FileResponse $streamDownload */
    $streamDownload = $client->send((new DownloadFileRequest(7))->withDownloadTo($sink))->dataOrFail();
    $sink->rewind();
    $downloads['sinkContent'] = $sink->getContents();
    $streamDownload->close();
    $downloads['sinkStillOpen'] = $sink->isWritable();
    $streamDownload = null;

    // Для TAR нужен ext-phar; содержимое архива — тот же report.txt.
    $transport->fake([
        DownloadFileRequest::class => MockResponse::file(__DIR__ . '/fixtures/report.tar', headers: [
            'Content-Type' => 'application/x-tar',
        ]),
    ]);
    /** @var FileResponse $archiveFile */
    $archiveFile = $client->send(new DownloadFileRequest(8))->dataOrFail();
    $archive = $archiveFile->asArchive();
    $entry = $archive->get('report.txt');
    if ($entry === null) {
        throw new RuntimeException('В учебном архиве нет report.txt');
    }
    $entry->saveTo($directory . '/extracted.txt');
    $archives = [
        'isArchive' => $archiveFile->isArchive(),
        'format' => $archive->getFormat(),
        'entryName' => $entry->name,
        'entryContent' => $entry->contents(),
        'savedContent' => file_get_contents($directory . '/extracted.txt'),
    ];
    // ArchiveEntry также удерживает архив; освобождаем обе ссылки и его временный файл.
    unset($entry, $archive);
} finally {
    $download?->close();
    $streamDownload?->close();
    $archiveFile?->close();
    $sink->close();
    foreach (['report.txt', 'extracted.txt'] as $name) {
        if (is_file($directory . '/' . $name)) {
            unlink($directory . '/' . $name);
        }
    }
    rmdir($directory);
}

echo json_encode([
    'uploads' => $uploads,
    'downloads' => $downloads,
    'archive' => $archives,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
