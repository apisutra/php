<?php

declare(strict_types=1);

use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Serialization\Hydrator;
use Example\Records\Config\ClientConfigFactory;
use Example\Records\Config\HydrationConfigFactory;
use Example\Records\AttributeExample\RecordDto;
use Example\Records\DemoClient;
use Example\Records\Resources\Records\Get\Dto\DocumentAttachmentDto;
use Example\Records\Resources\Records\Get\Dto\ImageAttachmentDto;
use Example\Records\Resources\Records\Get\Dto\TagDto;
use Example\Records\Resources\Records\Get\GetRecordRequest;
use Example\Records\Resources\Records\Get\GetRecordResponseDto;

require __DIR__ . '/bootstrap.php';

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

// Каждая неправильная копия меняет одно условие. Весь путь, включая unwrap,
// проходит через настоящий клиент; сеть по-прежнему заменена MockTransport.
$invalid = [];
$invalid['nested_contact'] = $success;
$invalid['nested_contact']['data']['author']['contact']['email'] = 42;
$invalid['collection_item'] = $success;
$invalid['collection_item']['data']['tags'][1]['id'] = '2';
$invalid['unknown_variant'] = $success;
$invalid['unknown_variant']['data']['assets'][0]['value']['type'] = 'audio';
$invalid['variant_field'] = $success;
$invalid['variant_field']['data']['assets'][0]['value']['width'] = '640';
$invalid['list_item'] = $success;
$invalid['list_item']['data']['related_ids'][1] = '12';
$invalid['custom_cast'] = $success;
$invalid['custom_cast']['data']['reading_time'] = '03:99';
$invalid['missing_id'] = $success;
unset($invalid['missing_id']['data']['record_id']);
$invalid['null_primary'] = $success;
$invalid['null_primary']['data']['record_id'] = null;
$invalid['null_primary']['data']['id'] = 8;
$invalid['null_revision'] = $success;
$invalid['null_revision']['data']['revision'] = null;
$invalid['constructor_value'] = $success;
$invalid['constructor_value']['data']['kind'] = 'other';
$invalid['enum'] = $success;
$invalid['enum']['data']['state'] = 'unknown';
$invalid['date'] = $success;
$invalid['date']['data']['created_at'] = 'yesterday';

$transport->fake([
    GetRecordRequest::class => MockResponse::sequence(array_map(
        static fn (array $payload): MockResponse => MockResponse::success($payload),
        array_values($invalid),
    )),
]);
$diagnostics = [];
foreach ($invalid as $case => $payload) {
    $execution = $client->records()->get(7)->send()->raw();
    $problem = $execution->errors->first();
    if (!$execution->isFailed() || $problem === null) {
        throw new RuntimeException('Ожидалась ошибка сценария ' . $case);
    }

    // Логический путь DTO и JSON Pointer в исходном ответе — разные полезные координаты.
    $diagnostics[$case] = [
        'code' => $problem->code->value,
        'reason' => $problem->context['reason'] ?? null,
        'path' => $problem->context['path'] ?? null,
        'sourcePath' => $problem->context['sourcePath'] ?? null,
        'sourcePathKind' => $problem->context['sourcePathKind'] ?? null,
        'httpStatus' => $execution->response?->status,
    ];
}

$attachments = array_map(
    static fn (ImageAttachmentDto|DocumentAttachmentDto $attachment): array => [
        'class' => (new ReflectionClass($attachment))->getShortName(),
        'type' => $attachment->type,
        'details' => $attachment instanceof ImageAttachmentDto
            ? ['width' => $attachment->width, 'height' => $attachment->height]
            : ['pages' => $attachment->pages, 'preview' => $attachment->preview->content()],
        'extra' => $attachment->_extra,
    ],
    $record->attachments,
);

// Вывод сгруппирован: объекты приложения, сериализация, defaults и диагностика ошибок.
echo json_encode([
    'dto' => [
        'id' => $record->id,
        'title' => $record->title,
        'createdAt' => $record->createdAt->format(DATE_ATOM),
        'status' => ['value' => $record->status->value, 'title' => $record->status->title()],
        'author' => [
            'name' => $record->author->displayName,
            'email' => $record->author->contact->email,
            'phone' => $record->author->contact->phone,
            'locale' => $record->author->contact->locale,
        ],
        'tags' => $record->tags->mapToArray(static fn (TagDto $tag): string => $tag->name),
        'attachments' => $attachments,
        'relatedIds' => $record->relatedIds,
        'rating' => $record->rating,
        'readingTimeSeconds' => $record->readingTimeSeconds,
        'externalId' => $record->externalId,
        'featured' => $record->featured,
        'description' => $record->description,
        'updatedAt' => $record->updatedAt,
        'revision' => $record->revision,
        'extra' => $record->_extra,
    ],
    'serialized' => $serialized,
    'copy' => ['originalTitle' => $record->title, 'newTitle' => $copy->title],
    'standalone' => [
        'sameData' => $standalone->toArray() === $serialized,
        'fromId' => $attributeRecord->id,
    ],
    'defaults' => [
        'fallbackId' => $fallback->id,
        'missingTitle' => $fallback->title,
        'nullTitle' => $nullTitle->title,
        'tagCount' => $fallback->tags->count(),
        'description' => $fallback->description,
        'updatedAt' => $fallback->updatedAt,
        'revision' => $fallback->revision,
        'explicitAuthorName' => $fallback->author->displayName,
    ],
    'httpError' => ['failed' => $error->isFailed(), 'status' => $error->errorStatus()],
    'hydrationErrors' => $diagnostics,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
