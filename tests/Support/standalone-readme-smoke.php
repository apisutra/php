<?php

declare(strict_types=1);

use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Example\Records\Config\ClientConfigFactory;
use Example\Records\DemoClient;
use Example\Records\Resources\Records\Get\GetRecordRequest;

$checkout = $argv[1] ?? dirname(__DIR__, 2);
// Выполняется опубликованный пример из проверяемого дистрибутива.
ob_start();
require $checkout . '/docs/example/sdk/run.php';
$output = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
$expected = [
    'id' => 7,
    'title' => 'Первая запись',
    'createdAt' => '2026-09-15T10:30:00+00:00',
    'authorName' => 'Анна',
    'description' => null,
    'extra' => ['future_flag' => false],
    'failed' => true,
    'status' => 404,
];
if ($output !== $expected) {
    throw new RuntimeException('Опубликованный SDK вернул неверные данные или ошибку');
}

// Тот же опубликованный запрос принимает запасной ключ и сохраняет непустое описание.
$fallbackTransport = new MockTransport();
$fallbackTransport->preventStrayRequests();
$fallbackTransport->fake([
    GetRecordRequest::class => MockResponse::success(['data' => [
        'id' => 8,
        'title' => 'Запасной формат',
        'created_at' => '2026-09-15T10:30:00+00:00',
        'author' => ['name' => 'Борис', 'role' => 'Редактор'],
        'description' => 'Описание записи',
    ]]),
]);
$fallbackClient = new DemoClient(ClientConfigFactory::create(), $fallbackTransport);
$fallbackRecord = $fallbackClient->records()->get(8)->send()->dataOrFail();
if (
    $fallbackRecord->id !== 8 || $fallbackRecord->authorName !== 'Борис'
    || $fallbackRecord->description !== 'Описание записи'
    || $fallbackRecord->_extra !== ['author' => ['role' => 'Редактор']]
) {
    throw new RuntimeException('Fallback, вложенное поле или нормализация описания работают неверно');
}
echo "Standalone published SDK — OK.\n";
