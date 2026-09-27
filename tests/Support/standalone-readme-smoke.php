<?php

declare(strict_types=1);

use Example\Records\Resources\Records\Get\Dto\AuthorDto;
use Example\Records\Resources\Records\Get\Dto\ContactDto;
use Example\Records\Resources\Records\Get\Dto\DocumentAttachmentDto;
use Example\Records\Resources\Records\Get\Dto\ImageAttachmentDto;
use Example\Records\Resources\Records\Get\Dto\RawAttachmentDto;
use Example\Records\Resources\Records\Get\Dto\TagCollection;
use Example\Records\Resources\Records\Get\RecordStatus;
use Example\Records\Webhook\RecordPayload;

$checkout = $argv[1] ?? dirname(__DIR__, 2);
// Выполняется опубликованный пример из проверяемого дистрибутива.
ob_start();
require $checkout . '/docs/example/sdk/run.php';
$output = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Опубликованный SDK: ' . $message);
    }
};
$check(($output['dto']['id'] ?? null) === 7, 'не получена запись');

// Проверяем настоящие объекты, а не только внешне правдоподобный JSON вывода.
$check(
    $record->author instanceof AuthorDto && $record->author->contact instanceof ContactDto
    && $record->tags instanceof TagCollection && $record->tags->first()->name === 'php'
    && $record->attachments[0] instanceof ImageAttachmentDto
    && $record->attachments[1] instanceof DocumentAttachmentDto
    && $record->attachments[2] instanceof RawAttachmentDto
    && $record->cover instanceof ImageAttachmentDto
    && $record->status === RecordStatus::Published,
    'потеряны типы вложенного графа',
);
$check(
    $record->readingTimeSeconds === 185 && $record->attachments[1]->preview->content() === 'SDK manual'
    && $record->externalId === '18446744073709551616'
    && $record->author->displayName === 'Анна Петрова' && $record->author->contact->phone === null,
    'не сработали cast, provider, точный ID или нормализация',
);
$serialized = $output['serialized'];
$check(
    $serialized['record_id'] === 7 && $serialized['state'] === 'published'
    && $serialized['created_at'] === '2026-09-15T10:30:00+00:00'
    && $serialized['reading_time'] === '03:05'
    && $serialized['localized_titles'] === ['en' => 'First record', 'ru' => 'Первая запись']
    && $serialized['author']['first_name'] === 'Анна'
    && array_key_exists('phone', $serialized['author']['contact'])
    && $serialized['author']['contact']['phone'] === null
    && $serialized['tags'][0]['label'] === 'php'
    && $serialized['assets'][1]['preview_file'] === 'U0RLIG1hbnVhbA=='
    && array_key_exists('updated_at', $serialized) && $serialized['updated_at'] === null,
    'toArray() не выполнил правила сериализации',
);
$check(
    $serialized['_extra'] === [
        'assets' => [
            ['sourceKey' => 0, 'remainder' => ['rank' => 1]],
            ['sourceKey' => 1, 'remainder' => ['rank' => 2]],
            ['sourceKey' => 2, 'remainder' => ['rank' => 3]],
        ],
        'metrics' => ['votes' => 0],
        'future_flag' => false,
        'future_null' => null,
        'future_list' => [],
    ]
    && $serialized['author']['_extra'] === ['role' => 'editor']
    && $serialized['author']['contact']['_extra'] === ['verified' => false]
    && $serialized['tags'][0]['_extra'] === ['color' => 'blue']
    && $serialized['assets'][0]['_extra'] === ['alt' => 'Обложка']
    && $serialized['assets'][1]['_extra'] === ['checksum' => 'demo'],
    'потеряны дополнительные данные корня, обёрток или вложенных объектов',
);
$check($output['standalone'] === [
    'sameData' => true,
    'fromId' => 7,
    'variant' => ['class' => 'RawAttachmentDto', 'raw' => ['type' => 'audio', 'duration' => 12]],
], 'разошлись HTTP и standalone или не работает выбор у корня JSON');
$check(
    $output['webhook']['sameData'] === true && $webhook->data->cover instanceof ImageAttachmentDto
    && $standalone !== $record && $standalone !== $webhook->data,
    'потерян граф webhook или самостоятельный PHP-вход',
);
$check($record->localizedTitles === ['en' => 'First record', 'ru' => 'Первая запись'], 'искажён словарь названий');
// Пустой JSON object допустим до cast; исходные [] проверяются ниже как ошибочный сценарий.
$emptyDictionary = $hydrator->hydrateJson(json_encode([
    'data' => array_replace($success['data'], ['localized_titles' => (object) []]),
], JSON_THROW_ON_ERROR), RecordPayload::class);
$check($emptyDictionary->data->localizedTitles === [], 'пустой JSON-словарь ошибочно отклонён');
$raw = [
    'type' => 'audio', 'url' => 'https://assets.example.test/clip.mp3', 'duration' => 12,
    'enabled' => false, 'caption' => null, 'channels' => [],
];
$check(
    $record->attachments[2]->raw === $raw && $serialized['assets'][2]['raw'] === $raw,
    'fallback потерял данные при чтении или toArray()',
);
$check(
    $output['copy'] === ['originalTitle' => 'Первая запись', 'newTitle' => 'Обновлённая запись']
    && $copy !== $record && $copy->author === $record->author,
    'with() изменил исходный DTO или без причины скопировал вложенный объект',
);
$check($output['defaults'] === [
    'fallbackId' => 8,
    'missingTitle' => 'Без названия',
    'nullTitle' => 'Без названия',
    'tagCount' => 0,
    'description' => 'Описание записи',
    'updatedAt' => null,
    'revision' => 0,
    'explicitAuthorName' => 'Редактор',
], 'нарушены правила missing/null/defaults или приоритет явного значения');
$check($output['httpError'] === ['failed' => true, 'status' => 404], 'не показан HTTP-отказ');
$errors = $output['hydrationErrors'];
$check(count($errors) === 17, 'пропущен сценарий некорректного ответа');
foreach ($errors as $case => $error) {
    $check($error['code'] === 'hydration_error' && $error['httpStatus'] === 200, 'неверная классификация ' . $case);
    $check(
        $error['webhook'] === ['reason' => $error['reason'], 'sourcePath' => $error['sourcePath']],
        'HTTP и webhook расходятся для ' . $case,
    );
}
$check(
    $errors['nested_contact']['path'] === 'data.author.contact.email'
    && $errors['nested_contact']['sourcePath'] === '/data/author/contact/email'
    && $errors['nested_contact']['sourcePathKind'] === 'resolved'
    && $errors['variant_field']['path'] === 'data.attachments[0].width'
    && $errors['variant_field']['sourcePath'] === '/data/assets/0/value/width'
    && $errors['custom_cast']['reason'] === 'invalid_reading_time'
    && $errors['constructor_value']['reason'] === 'constructor_value_mismatch',
    'диагностика не указывает нарушенное поле исходного ответа',
);
$check(
    $errors['invalid_discriminator']['reason'] === 'invalid_discriminator_type'
    && $errors['dictionary_shape']['reason'] === 'invalid_object_shape'
    && $errors['dictionary_shape']['sourcePath'] === '/data/localized_titles'
    && $errors['dictionary_value']['reason'] === 'invalid_localized_titles'
    && $errors['list_shape']['reason'] === 'invalid_list_shape'
    && $errors['numeric_object_as_list']['reason'] === 'invalid_list_shape'
    && $errors['dto_shape']['sourcePath'] === '/data/cover',
    'потеряна исходная форма или проверка discriminator',
);
echo "Standalone published SDK — OK.\n";
