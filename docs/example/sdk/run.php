<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Example\Records\AttributeExample\RecordDto;
use Example\Records\Config\ClientConfigFactory;
use Example\Records\DemoClient;
use Example\Records\Resources\Records\Get\Dto\AttachmentDto;
use Example\Records\Resources\Records\Get\GetRecordRequest;
use Example\Records\Resources\Records\Get\GetRecordResponseDto;
use Example\Records\Webhook\RecordPayload;

use function Example\Records\Demo\collectHydrationErrors;
use function Example\Records\Demo\describeAttachment;
use function Example\Records\Demo\describeDefaults;
use function Example\Records\Demo\describeRecord;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/demo/hydration-errors.php';
require_once __DIR__ . '/demo/output.php';

$transport = new MockTransport();
$transport->preventStrayRequests();
$successJson = file_get_contents(__DIR__ . '/fixtures/record.json');
$failureJson = file_get_contents(__DIR__ . '/fixtures/error.json');
if ($successJson === false || $failureJson === false) {
    throw new RuntimeException('Не удалось прочитать JSON-фикстуры');
}
$transport->fake([
    GetRecordRequest::class => MockResponse::sequence([
        MockResponse::make($successJson),
        MockResponse::make($failureJson, 404),
    ]),
]);
$config = ClientConfigFactory::create();
$client = new DemoClient($config, $transport);
// Выполнить запрос синхронно. send() возвращает ResultHandle — обёртку результата.
$handle = $client->records()->get(7)->send();

// Извлечь DTO, уже созданный по #[Returns]; при статусе FAILED — исключение.
/** @var GetRecordResponseDto $record */
$record = $handle->dataOrFail();
$error = $client->records()->get(404)->send()->resolved();

// Исходный JSON webhook разбирается с той же конфигурацией, что у клиента.
// RecordPayload описывает оболочку data, поэтому до hydrateJson() не нужен json_decode().
$hydrator = Hydrator::forConfig($config->hydration ?? new HydrationConfig(), $config->localization);
$webhook = $hydrator->hydrateJson($successJson, RecordPayload::class);
// Та же декларация вариантов работает у самого корня JSON.
$rootAttachment = $hydrator->hydrateJson('{"type":"audio","duration":12}', AttachmentDto::class);

// Отдельный PHP-вход для уже декодированных данных; исходную JSON-форму он не восстанавливает.
$success = json_decode($successJson, true, flags: JSON_THROW_ON_ERROR);
$standalone = $hydrator->hydrate($success['data'], GetRecordResponseDto::class);

// Маленькая отдельная модель показывает from() без клиента и внешних правил.
$attributeRecord = RecordDto::from($success['data']);

// toArray() сериализует DTO по атрибутам, включая вложенные модели и двусторонний cast.
// Это представление данных, а не восстановление исходного JSON байт в байт:
// например, timezone нормализуется, value-обёртки раскрываются, остатки остаются в _extra.
$serialized = $record->toArray();

// with() создаёт другой readonly DTO; исходная запись не меняется.
$copy = $record->with(title: 'Обновлённая запись');

// Отсутствие, null и пустая строка имеют разные правила.
$fallbackSource = $success['data'];
unset($fallbackSource['record_id'], $fallbackSource['title'], $fallbackSource['tags'], $fallbackSource['updated_at']);
$fallbackSource['id'] = 8;
$fallbackSource['description'] = 'Описание записи';
$fallbackSource['revision'] = 0;
$fallbackSource['author']['display_name'] = 'Редактор';
$fallback = $hydrator->hydrate($fallbackSource, GetRecordResponseDto::class);
$nullTitle = $hydrator->hydrate(array_replace($success['data'], ['title' => null]), GetRecordResponseDto::class);

// Некорректные ответы проверяются и через HTTP, и через JSON-вход webhook.
// Таблица изменений и чтение code/reason/path/sourcePath находятся в demo/hydration-errors.php.
$diagnostics = collectHydrationErrors($client, $transport, $hydrator, $success['data']);

// Вывод сгруппирован: объекты приложения, сериализация, defaults и диагностика ошибок.
echo json_encode([
    'dto' => describeRecord($record),
    'serialized' => $serialized,
    'copy' => ['originalTitle' => $record->title, 'newTitle' => $copy->title],
    'standalone' => [
        'sameData' => $standalone->toArray() === $serialized,
        'fromId' => $attributeRecord->id,
        'variant' => describeAttachment($rootAttachment),
    ],
    'webhook' => [
        'class' => $webhook::class,
        'sameData' => $webhook->data->toArray() === $serialized,
    ],
    'defaults' => describeDefaults($fallback, $nullTitle),
    'httpError' => ['failed' => $error->isFailed(), 'status' => $error->errorStatus()],
    'hydrationErrors' => $diagnostics,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
