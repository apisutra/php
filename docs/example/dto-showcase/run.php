<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Example\DtoShowcase\CatalogClient;
use Example\DtoShowcase\CatalogItemDto;
use Example\DtoShowcase\CatalogHydration;
use Example\DtoShowcase\GetCatalogItemRequest;
use Example\DtoShowcase\ImageDto;
use Example\DtoShowcase\SaveCatalogItemRequest;
use Example\DtoShowcase\VideoDto;

require __DIR__ . '/../sdk/bootstrap.php';

$source = json_decode((string) file_get_contents(__DIR__ . '/fixtures/item.json'), true, flags: JSON_THROW_ON_ERROR);
$hydration = CatalogHydration::create();
$hydrator = Hydrator::forConfig($hydration);
/** @var CatalogItemDto $item */
$item = $hydrator->hydrate($source, CatalogItemDto::class);

// Одна конфигурация работает standalone, при Returns и при сериализации запроса.
$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake([
    GetCatalogItemRequest::class => MockResponse::success(['data' => $source]),
    SaveCatalogItemRequest::class => MockResponse::success(['saved' => true]),
]);
$client = new CatalogClient(new ClientConfig(
    baseUrl: 'https://catalog.example.test',
    hydration: $hydration,
), $transport);
$fromResponse = $client->send(new GetCatalogItemRequest())->dataOrFail();
// JSON сравнивает значения вложенного plain DTO, а не идентичность экземпляров.
if (
    !$fromResponse instanceof CatalogItemDto
    || json_encode($fromResponse->toArray(), JSON_THROW_ON_ERROR) !== json_encode($item->toArray(), JSON_THROW_ON_ERROR)
) {
    throw new RuntimeException('Standalone и Returns дали разные данные');
}
$client->send(new SaveCatalogItemRequest($item))->dataOrFail();
$wire = json_decode((string) $transport->getRecorded()[1]->body, true, flags: JSON_THROW_ON_ERROR);

// Fallback, missing defaults, автоматическая пустая typed collection и непустая строка.
$fallbackSource = $source;
unset($fallbackSource['product_id'], $fallbackSource['title'], $fallbackSource['tags']);
$fallbackSource['id'] = 8;
$fallbackSource['description'] = 'Описание книги';
$fallbackSource['stock'] = 0;
$fallbackSource['manual_file'] = 'U0RLIG1hbnVhbA==';
/** @var CatalogItemDto $fallback */
$fallback = $hydrator->hydrate($fallbackSource, CatalogItemDto::class);

// Каждый сценарий меняет ровно одно условие исходного контракта.
$invalid = [];
$invalid['wrong_kind'] = array_replace($source, ['kind' => 'other']);
$invalid['missing_id'] = $source;
unset($invalid['missing_id']['product_id']);
$invalid['string_id'] = array_replace($source, ['product_id' => '7']);
$invalid['null_primary'] = array_replace($source, ['product_id' => null, 'id' => 8]);
$invalid['null_stock'] = array_replace($source, ['stock' => null]);
$invalid['wrong_list_item'] = array_replace($source, ['related_ids' => [11, '12']]);
$invalid['wrong_seller_id'] = array_replace($source, ['seller' => ['id' => '9', 'name' => 'Магазин']]);
$invalid['unknown_variant'] = $source;
$invalid['unknown_variant']['assets'][0]['value']['type'] = 'audio';
$invalid['invalid_date'] = array_replace($source, ['created_at' => 'yesterday']);
$invalid['invalid_price'] = array_replace($source, ['price' => '12,34']);
$errors = [];
foreach ($invalid as $case => $payload) {
    try {
        $hydrator->hydrate($payload, CatalogItemDto::class);
        throw new RuntimeException('Ожидалась ошибка сценария ' . $case);
    } catch (HydrationException $exception) {
        $errors[$case] = [
            'reason' => $exception->reason,
            'path' => $exception->path,
            'sourcePath' => $exception->sourcePath,
            'sourcePathKind' => $exception->sourcePathKind?->value,
        ];
    }
}

echo json_encode([
    'dto' => [
        'kind' => $item->kind,
        'id' => $item->id,
        'sku' => $item->sku,
        'title' => $item->title,
        'description' => $item->description,
        'available' => $item->available,
        'rating' => $item->rating,
        'createdAt' => $item->createdAt->format(DATE_ATOM),
        'status' => $item->status->value,
        'priceMinor' => $item->priceMinor,
        'manualContent' => $item->manual->content(),
        'manualSize' => $item->manual->size(),
        'seller' => $item->seller,
        'firstTag' => $item->tags->first()?->name,
        'tagCount' => $item->tags->count(),
        'relatedIds' => $item->relatedIds,
        'mediaTypes' => array_map(
            static fn (ImageDto|VideoDto $media): string => (new ReflectionClass($media))->getShortName(),
            $item->media,
        ),
        'displayName' => $item->displayName,
        'stock' => $item->stock,
        '_extra' => $item->_extra,
    ],
    'dx' => $item->toArray(),
    'wire' => $wire,
    'fallback' => [
        'id' => $fallback->id,
        'title' => $fallback->title,
        'description' => $fallback->description,
        'tagCount' => $fallback->tags->count(),
        'stock' => $fallback->stock,
        'manualContent' => $fallback->manual->content(),
    ],
    'errors' => $errors,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
