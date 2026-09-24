<?php

declare(strict_types=1);

use ApiSutra\Serialization\Hydrator;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Example\Records\AttributeExample\RecordDto;
use Example\Records\Config\ClientConfigFactory;
use Example\Records\Config\HydrationConfigFactory;
use Example\Records\DemoClient;
use Example\Records\Resources\Records\Get\GetRecordRequest;
use Example\Records\Resources\Records\Get\GetRecordResponseDto;

use function Example\Records\Demo\collectHydrationErrors;
use function Example\Records\Demo\describeDefaults;
use function Example\Records\Demo\describeRecord;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/demo/hydration-errors.php';
require_once __DIR__ . '/demo/output.php';

$transport = new MockTransport();
$transport->preventStrayRequests();
$success = json_decode(
    (string) file_get_contents(__DIR__ . '/fixtures/record.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$failure = json_decode(
    (string) file_get_contents(__DIR__ . '/fixtures/error.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$transport->fake([
    GetRecordRequest::class => MockResponse::sequence([
        MockResponse::success($success),
        MockResponse::make($failure, 404),
    ]),
]);
$client = new DemoClient(ClientConfigFactory::create(), $transport);
// Выполнить запрос синхронно. send() возвращает ResultHandle — обёртку результата.
$handle = $client->records()->get(7)->send();

// Извлечь DTO, уже созданный по #[Returns]; при статусе FAILED — исключение.
/** @var GetRecordResponseDto $record */
$record = $handle->dataOrFail();
$error = $client->records()->get(404)->send()->resolved();

// Тот же граф можно собрать без HTTP с явной конфигурацией гидратора.
$hydrator = Hydrator::forConfig(HydrationConfigFactory::create());
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

// Двенадцать некорректных ответов проходят через тот же клиент: raw() сохраняет детали отказа.
// Таблица изменений и чтение code/reason/path/sourcePath находятся в demo/hydration-errors.php.
$diagnostics = collectHydrationErrors($client, $transport, $success['data']);

// Вывод сгруппирован: объекты приложения, сериализация, defaults и диагностика ошибок.
echo json_encode([
    'dto' => describeRecord($record),
    'serialized' => $serialized,
    'copy' => ['originalTitle' => $record->title, 'newTitle' => $copy->title],
    'standalone' => [
        'sameData' => $standalone->toArray() === $serialized,
        'fromId' => $attributeRecord->id,
    ],
    'defaults' => describeDefaults($fallback, $nullTitle),
    'httpError' => ['failed' => $error->isFailed(), 'status' => $error->errorStatus()],
    'hydrationErrors' => $diagnostics,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
